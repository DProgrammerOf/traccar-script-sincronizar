<?php

    namespace Process;

    use Carbon\Carbon;
    use Illuminate\Database\Capsule\Manager;
    use Models\User;
    use Models\Poi;

    class MessagePoi
    {
        private $user;
        private $logger;

        public function __construct(User $user, Logger $logger)
        {
            $this->user = $user;
            $this->date = $GLOBALS['date_telemetria_carbon'];
            $this->logger = $logger;
            echo "Poi calculate [CLIENTE ". $this->user->id ."]\n";
        }

        public function create(Manager $Eloquent): ?\PDOException
        {
            if ($Eloquent::schema('tracker')->hasTable('message_poi_'.$this->user->id) == false)
            {
                try {
                    $Eloquent::schema('tracker')->create('message_poi_'.$this->user->id, function ($table) {
                        $table->increments('id');
                        $table->integer('poiId');
                        $table->integer('deviceId');
                        $table->dateTime('dateIn');
                        $table->dateTime('dateOut')->nullable();
                        $table->string('type', 255);
                        $table->string('attributes', 4000);
                        $table->index(['id', 'deviceId', 'poiId', 'dateIn', 'dateOut']);
                    });
                   echo "Created table: message_poi_".$this->user->id." \n";
                } catch (\PDOException $e) {
                    if ($e->getCode() != "42S01") {
                        return $e;
                    }
                }
            }

            return null;
        }

        public function distance($lat1, $lon1, $lat2, $lon2)
        {
            $lat1 = floatval($lat1);
            $lon1 = floatval($lon1);
            $lat2 = floatval($lat2);
            $lon2 = floatval($lon2);

            $theta = $lon1 - $lon2;
            $miles = (sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)));
            $miles = acos($miles);
            $miles = rad2deg($miles);
            $miles = $miles * 60 * 1.1515;
            $kilometers = $miles * 1.609344;
            return $kilometers * 1000;
        }

        public function correctMessagePoi($poi, $message_poi, $period):\Models\MessagePoi {
            foreach ($period->where('date', '>', $message_poi->dateIn) as $gprmc) {
                $distancia = $this->distance(
                    $gprmc->latitudeDecimalDegrees, $gprmc->longitudeDecimalDegrees,
                    $poi->latitude, $poi->longitude
                );

                // Fora do Ponto
                if ( $distancia > $poi->raio ) {
                    $message_poi->dateOut = $gprmc->date;
                    break;
                }
            }

            return $message_poi;
        }

        public function verifyDateOutNullable($vehicle, $pois, $daily): void
        {
            $yesterday = $GLOBALS['date_telemetria_carbon']->subDays(1);
            $period = [];
            $MessagesPoi = new \Models\MessagePoi($this->user->id);
            $MessagesPoi = $MessagesPoi->where('deviceId', $vehicle->id)->where('dateOut', null)->orderBy('dateIn', 'asc')->get();
            if ($MessagesPoi->count() <= 0) {
                return;
            }
            //echo "verifyDateOutNullable".PHP_EOL;
            if ($yesterday->isSameDay($MessagesPoi[0]->dateIn)) {
                $period = $daily;
                //echo "isYesterdayToYesterday".PHP_EOL;
            } else {
                $period = $vehicle->dailyPeriodAll($MessagesPoi[0]->dateIn);
                //echo "isOldYesterday, period start ".$period->first()->date." to ".$period->last()->date.PHP_EOL;
            }
            foreach ($MessagesPoi as $message_poi) {
                $message_poi = $this->correctMessagePoi(
                    $pois->firstWhere('id', $message_poi->poiId),
                    $message_poi,
                    $period
                );
                echo "@verifyDateOutNullable messagePoi $message_poi->id processed to dateOut is $message_poi->dateOut".PHP_EOL;
                $message_poi->save();
            }
        }

        public function calculatePoi($vehicle, $daily): void
        {
            echo "Processing: ".$vehicle->name.PHP_EOL;
            $Pois = new Poi();
            $Pois = $Pois->where('cliente', $this->user->id)->get();
            if ($Pois->count() == 0) {
                return;
            }

            // Verify PoI´s dateOut nullable before yesterday and calculate
            $this->verifyDateOutNullable($vehicle, $Pois, $daily);

            // PoI´s with dateOut in yesterday
            $MessagesPoiDB = new \Models\MessagePoi($this->user->id);
            $MessagesPoiAll = $MessagesPoiDB->get();
            $MessagesPoiCorrected = $MessagesPoiDB->where('deviceId', $vehicle->id)->where('dateOut', '>=', $daily->first()->date)->orderBy('dateOut', 'asc')->get();
            $MessagesPoiOpened = $MessagesPoiDB->where('deviceId', $vehicle->id)->where('dateOut', null)->get();

            // Calculate PoI´s yesterday
            $MessagesPoi = [];
            $MessagesPoiSaved = 0;
            $indexMP = 0;
            foreach ($Pois as $poi) {
                $isOpened = $MessagesPoiOpened->firstWhere('poiId', $poi->id);
                if (!is_null($isOpened)) {
                    continue;
                }
                $dentroPoi = false;
                $dateCorrected = $MessagesPoiCorrected->firstWhere('poiId', $poi->id);
                $dateCorrected = !is_null($dateCorrected) ? $dateCorrected->dateOut : $poi->created_at;
                foreach ($daily->where('date', '>=', $dateCorrected) as $gprmc) {
                    $distancia = $this->distance(
                        $gprmc->latitudeDecimalDegrees, $gprmc->longitudeDecimalDegrees,
                        $poi->latitude, $poi->longitude
                    );

                    // Dentro do Ponto
                    if ( $distancia < $poi->raio ) {
                        if ($dentroPoi)
                            continue;

                        // Criação Message
                        $message_poi = new \Models\MessagePoi($this->user->id);
                        $message_poi->poiId = $poi->id;
                        $message_poi->deviceId = $vehicle->id;
                        $message_poi->dateIn = $gprmc->date;
                        $message_poi->dateOut = null;
                        $message_poi->type = $poi->tipo;
                        $message_poi->attributes = [
                            'latitude' => $gprmc->latitudeDecimalDegrees,
                            'longitude' => $gprmc->longitudeDecimalDegrees,
                            'ignition' => $gprmc->ligado == "S",
                            'speed' => $gprmc->speed,
                            'poi.descricao' => $poi->descricao,
                            'poi.observacoes' => $poi->observacao
                        ];

                        $MessagesPoi[$indexMP] = $message_poi;
                        $dentroPoi = true;
                    } else {
                        if ($dentroPoi) {
                            $MessagesPoi[$indexMP]->dateOut = $gprmc->date;

                            $indexMP++;
                            $dentroPoi = false;
                        }
                    }
                }

                if ( $indexMP < count($MessagesPoi) ) {
                    if ($MessagesPoi[$indexMP]->dateOut == null) {
                        $indexMP++;
                    }
                }
            }

            foreach ($MessagesPoi as $message_poi) {
                try {
                    $MessagePoiDB = new \Models\MessagePoi($this->user->id);
                   if ($MessagePoiDB->where('poiId', $message_poi->poiId)->where('deviceId', $message_poi->deviceId)->where('dateIn', $message_poi->dateIn)->count() > 0)
                       continue;
                       
                    $message_poi->save();
                    $MessagesPoiSaved++;
                } catch (\PDOException $e) {
                    echo "Poi error calculated [CLIENTE ".$this->user->id."][ERROR ".$e->getMessage()."]\n";
                }
            }
            echo $Pois->count()." poi´s calculated, result in ".count($MessagesPoi)." message´s, saved in database total = ".$MessagesPoiSaved.PHP_EOL;
        }
    }
