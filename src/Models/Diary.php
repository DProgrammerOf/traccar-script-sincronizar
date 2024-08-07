<?php

    namespace Models;

    class Diary extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'bem_diario';
        protected $connection = 'tracker';

        public function getKm()
        {
            return $this->km_rodado;
        }
    }