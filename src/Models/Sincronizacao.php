<?php

    namespace Models;

    class Sincronizacao extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'cliente_sincronizacao';
        protected $connection = 'renan_tracker';
        public const STATUS_WAIT = 0;
        public const STATUS_RUN = 1;
        public const STATUS_FINISH = 2;
        public const STATUS_FAIL = 3;

        public function running($cliente_id)
        {
            return $this
                ->where('cliente_id', $cliente_id)
                ->where('status', self::STATUS_WAIT)
                ->firstOrFail();
        }
    }