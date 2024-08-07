<?php

    namespace Models;

    class User extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'cliente';
        protected $connection = 'tracker';

        public function vehicles(): \Illuminate\Database\Eloquent\Relations\HasMany
        {
            return $this->hasMany(Vehicle::class, 'cliente');
        }

        public function telemetry(): ?Telemetry
        {
            try {
                $instance = new Telemetry( $this->id );
                return $instance->first();
            } catch (\PDOException $e) {
                // SQLERROR[42S02] == Table don't exists
                if ($e->getCode() != "42S02") {
                    return $e;
                }
            }
            return null;
        }

    }