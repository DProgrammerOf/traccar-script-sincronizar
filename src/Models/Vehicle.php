<?php

    namespace Models;

    use Carbon\Carbon;

    class Vehicle extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'bem';
        protected $connection = 'tracker';

        public function daily(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            $instance = new Gprmc($this->imei);
            $today = $GLOBALS['date_telemetria_carbon']->format("Y-m-d");
            return $this
                ->newHasMany($instance->newQuery(), $this,'imei','imei')
                ->whereBetween("date", [$today." 00:00", $today." 23:59" ])
                ->where("ligado", "S");
        }

        public function dailyPeriodAll($start): ?\Illuminate\Database\Eloquent\Collection
        {
            $instance = new Gprmc($this->imei);
            $today = $GLOBALS['date_telemetria_carbon']->format("Y-m-d");
            $start = (new Carbon($start))->format("Y-m-d H:i");
            return $instance
                ->whereBetween("date", [$start, $today." 23:59" ])
                ->orderBy("date", "asc")
                ->get();
        }

        public function dailyAll(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            $instance = new Gprmc($this->imei);
            $today = $GLOBALS['date_telemetria_carbon']->format("Y-m-d");
            return $this
                ->newHasMany($instance->newQuery(), $this,'imei','imei')
                ->whereBetween("date", [$today." 00:00", $today." 23:59" ])
                ->orderBy("date", "asc");
        }

        public function dailyBeforeAll(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            $instance = new Gprmc($this->imei);
            $today = $GLOBALS['date_telemetria_carbon']->subDays(1)->format("Y-m-d");  
            return $this
                ->newHasMany($instance->newQuery(), $this,'imei','imei')
                ->whereBetween("date", [$today." 00:00", $today." 23:59" ])
                ->orderBy("date", "asc");
        }

        public function km_daily(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            $today = $GLOBALS['date_telemetria_carbon']->format("Y-m-d");
            return $this
                ->hasMany(Diary::class, 'deviceid')
                ->whereDate("data", $today);
        }

        public function odometer_active(): bool
        {
            return $this->calc_hodometro === 1;
        }
    }
