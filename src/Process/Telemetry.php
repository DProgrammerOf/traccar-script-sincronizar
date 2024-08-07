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

        public function calculate($poi): void
        {
            foreach ($this->user->vehicles as $vehicle) {
                // if ($vehicle->imei !== '354522181983714') continue;
                $daily_all = $vehicle->dailyAll;

                // Calculate PoI´s
                if (!is_null($poi) && $daily_all->count() > 0) {
                    $poi->calculatePoi($vehicle, $daily_all);
                }
                
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
                    "km" => ( $vehicle->km_daily->count() == 0 ) ? 0 : $vehicle->km_daily[0]->km_rodado,
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
                $telemetry->save();
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
