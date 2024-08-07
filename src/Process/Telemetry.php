<?php
    declare(strict_types=1);

    namespace Process;

    use Carbon\Carbon;
    use Illuminate\Database\Capsule\Manager;
    use Models\User;

    class Telemetry {
        private $user;
        private $telemetry = array("veiculos" => []);
        private $date;
        private $logger;

        public function __construct(User $user, Logger $logger)
        {
            $this->user = $user;
            $this->date = $GLOBALS['date_telemetria_carbon'];
            $this->logger = $logger;
        }

        public function create(Manager $Eloquent): ?\PDOException
        {
            if ($Eloquent::schema('tracker')->hasTable('telemetria_cliente_'.$this->user->id) == false)
            {
                try {
                    $Eloquent::schema('tracker')->create('telemetria_cliente_'.$this->user->id, function ($table) {
                        $table->increments('id');
                        $table->date('date')->unique();
                        $table->longText('general');
                        $table->longText('behavior')->nullable();
                    });
                    $this->logger->save("Telemetry", "Created table: telemetria_cliente_%d \n", $this->user->id);
                } catch (\PDOException $e) {
                    if ($e->getCode() != "42S01") {
                        return $e;
                    }
                }
            }

            return null;
        }

        /**
         * Calculate distances of device by first and last odometer in day
         * @param mixed $gprmc
         * @return int
         */
        public function recalculate_distance_by_odometer($gprmc): int
        {
            $length = $gprmc->count();
            
            // get first odometer recv from device
            $first_odom = null;
            for ($i=0; $i < $length; $i++) {
                //echo "FirstOffset: ".$i.PHP_EOL;
                $pos = $gprmc[$i];
                $attr = json_decode($pos->attributes);
                if (isset($attr->odometer)) {
                    $first_odom = (int)$attr->odometer;
                    break;
                }
            }

            // get first odometer recv from device
            $last_odom = null;
            for ($i=1; $i < $length; $i++) {
                //echo "LastOffset: ".$i.PHP_EOL;
                $pos = $gprmc[$length-$i];
                $attr = json_decode($pos->attributes);
                if (isset($attr->odometer)) {
                    $last_odom = (int)$attr->odometer;
                    break;
                }
            }
            
            // km to meters odometer
            return ($last_odom - $first_odom) * 1000;
        }

        /**
         * Calculate distances of device by point-to-point in day
         * @param mixed $gprmc
         * @return int
         */
        public function recalculate_distance_by_point($gprmc): int
        {
            function distance($lat1, $lon1, $lat2, $lon2) {
                $theta = $lon1 - $lon2;
                $miles = (sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)));
                $miles = acos($miles);
                $miles = rad2deg($miles);
                $miles = $miles * 60 * 1.1515;
                $feet = $miles * 5280;
                $yards = $feet / 3;
                $kilometers = $miles * 1.609344;
                $meters = $kilometers * 1000;
                return $meters;
            }
            $length = $gprmc->count();
            
            // get first odometer recv from device
            $distance = 0;
            $last_pos = $gprmc[0];
            for ($i=1; $i < $length; $i++) {
                $pos = $gprmc[$i];
                // verify is same location
                // if is true -> replace with last and skip to next
                // else -> calculate distance current $pos with $last_pos
                if ($pos && $last_pos) {
                    [ $latitude, $longitude, $last_latitude, $last_longitude ] = [
                        floatval($pos->latitudeDecimalDegrees), floatval($pos->longitudeDecimalDegrees),
                        floatval($last_pos->latitudeDecimalDegrees), floatval($last_pos->longitudeDecimalDegrees)
                    ];
                    //
                    $last_pos = $pos;
                    if ( $latitude == $last_latitude && $longitude == $last_longitude ) {
                        continue;
                    }
                    $distance += is_nan(
                        distance($last_latitude, $last_longitude, $latitude, $longitude)
                    ) === false ? distance($last_latitude, $last_longitude, $latitude, $longitude) : 0;
                }
            }
            
            return (int)$distance;
        }

        /**
         * Calculate distance by positions from device
         * @param mixed $daily_all
         * @param mixed $odometer_active
         * @return int
         */
        public function recalculate_diary($daily_all, $odometer_active): int
        {
            $type = $odometer_active ? 'odometer' : 'by_point';
            $distance = $odometer_active 
            ? $this->recalculate_distance_by_odometer($daily_all) 
            : $this->recalculate_distance_by_point($daily_all);
            $this->logger->save( "Telemetry", "recalculate_diary {$type} {$daily_all[0]->date} distance: {$distance}m\n");
            return $distance;
        }

        public function calculate(): void
        {
            
            foreach ($this->user->vehicles as $vehicle) {
                if ($vehicle->id !== 600500) continue;
                $this->logger->save( "Telemetry", "calculate vehicle {$vehicle->name}\n");
                $daily_all = $vehicle->dailyAll;
                // Calculate Telemetry
                $daily = $daily_all->where("ligado", "S");
                $moved = false;
                $turn_on = false;
                $vel_max = array( 
                    'velocidade' => 0, 
                    'latitude' => null, 
                    'longitude' => null, 
                    'date' => null, 
                    'id' => null 
                );
                $vel_avg = 0;

                // Calculate Jornada
                $first_move = "00:00:00";
                $last_move = "00:00:00";
                $time_move = "00:00:00";
                $time_standby = "00:00:00";

                // If the vehicle is move in day
                if ($daily->count() > 0) {
                    // @Jornada
                	if ($daily->where('speed', '>', 0)->count() > 0) {
                    	$first_move = Carbon::createFromFormat('Y-m-d H:i:s', $daily->where('speed', '>', 0)->first()->date)->format('H:i:s');
                    	$last_move =  Carbon::createFromFormat('Y-m-d H:i:s', $daily->last()->date)->format('H:i:s');
                    }

                    // calculate $time_move vehicle in day
                    $TempoMovimento = new \DateTime('00:00:00');
                    $Andou = null;
                    $Parou = null;
                    foreach($daily_all as $movimento) {
                        if ($movimento->ligado == "S" && is_null($Andou)) {
                            $Andou = Carbon::createFromFormat('Y-m-d H:i:s', $movimento->date);
                        }

                        if ($movimento->ligado == "N" && is_null($Parou) && !is_null($Andou)) {
                            $Parou = Carbon::createFromFormat('Y-m-d H:i:s', $movimento->date);
                        }

                        if (!is_null($Andou) && !is_null($Parou)) {
                            $TempoMovimento->add($Andou->diff($Parou));
                            $Andou = null;
                            $Parou = null;
                        }
                    }
                    $time_move = $TempoMovimento->format("H:i:s");

                    // calculate $time_santby vehicle in day (ignition on and speed == 0)
                    $TempoParado = new \DateTime('00:00:00');
                    $Andou = null;
                    $Parou = null;
                    foreach ($daily_all as $movimento) {
                        if ($movimento->ligado == "N" && !is_null($Parou)) {
                            $Desligou = Carbon::createFromFormat('Y-m-d H:i:s', $movimento->date);
                            $TempoParado->add($Parou->diff($Desligou));
                            $Andou = null;
                            $Parou = null;
                            continue;
                        }

                        if ($movimento->ligado == "S" && $movimento->speed == 0 && is_null($Parou)) {
                            $Parou = Carbon::createFromFormat('Y-m-d H:i:s', $movimento->date);
                        }

                        if ($movimento->ligado == "S" && $movimento->speed > 0 && is_null($Andou) && !is_null($Parou)) {
                            $Andou = Carbon::createFromFormat('Y-m-d H:i:s', $movimento->date);
                        }

                        if (!is_null($Andou) && !is_null($Parou)) {
                            $TempoParado->add($Parou->diff($Andou));
                            $Parou = null;
                            $Andou = null;
                        }
                    }
                    $time_standby = $TempoParado->format("H:i:s");

                    // @Telemetry
                    $turn_on = true;
                    if ($daily->where("speed", ">", 0)->count() > 0) {
                        $moved = true;
                        $vel_max_gprmc = $daily->sortByDesc("speed")->first();
                        $vel_max = array( 
                            'velocidade' => $vel_max_gprmc->speed, 
                            'latitude' => $vel_max_gprmc->latitudeDecimalDegrees, 
                            'longitude' => $vel_max_gprmc->longitudeDecimalDegrees, 
                            'date' => $vel_max_gprmc->date, 
                            'id' => $vel_max_gprmc->id 
                        );
                        $vel_avg = (int) ($daily->where("speed", ">", 0)->sum('speed') / $daily->where("speed", ">", 0)->count());
                    }
                }

                $this->telemetry["veiculos"][] = array (
                    "id" => $vehicle->id,
                    "placa" => $vehicle->name,
                    "km" => $this->recalculate_diary($daily_all, $vehicle->odometer_active()),
                    "maxima" => $vel_max,
                    "velocidade_media" => $vel_avg,
                    "movimentou" => $moved,
                    "ligou" => $turn_on,
                    "jornada" => [
                        'primeiro_movimento' => $first_move,
                        'ultimo_movimento' => $last_move,
                        'tempo_movimento' => $time_move,
                        'tempo_parado_ligado' => $time_standby
                    ]
                );
                unset($daily_all);
            }
            try {
                $telemetry = new \Models\Telemetry( $this->user->id );
                $telemetry->date = $this->date;
                $telemetry->general = $this->telemetry;
                //$telemetry->save();
                $this->finish();
            } catch (\PDOException $e) {
                $this->logger->save( "Telemetry", "Telemetry error calculated [CLIENTE %d][ERROR %s]\n", $this->user->id, $e->getMessage() );
            }
        }

        public function finish(): void
        {
            $this->logger->save( "Telemetry", "Telemetry calculated [CLIENTE: %d]\n", $this->user->id);
            $this->logger->save( "Telemetry", json_encode($this->telemetry) );
            $this->logger->save( "Telemetry", PHP_EOL);
        }

    }
