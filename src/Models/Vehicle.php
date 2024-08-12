<?php

    namespace Models;

    class Vehicle extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'bem';
        protected $connection = 'tracker';

        public function dailyAll($date): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            $instance = new Gprmc($this->imei);
            return $this
                ->newHasMany($instance->newQuery(), $this,'imei','imei')
                ->whereBetween("date", [$date." 00:00", $date." 23:59" ])
                ->orderBy("date", "asc");
        }

        public function odometer_active(): bool
        {
            return $this->calc_hodometro === 1;
        }
    }
