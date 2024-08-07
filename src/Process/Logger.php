<?php

    namespace Process;

    use Carbon\Carbon;

    class Logger
    {
        private $date;
        private $dir;

        public function __construct($dir)
        {
            $this->date = $GLOBALS['date_telemetria_carbon'];
            $this->dir = $dir;
        }

        public function save($filename, $text, $id=null, $error=null): void
        {
            $text = sprintf($text, $id, $error);
            file_put_contents(
                $this->dir . '/logs/'.$filename.'_Log_' . $this->date->format("d-m-Y"),
                $text,
                FILE_APPEND
            );
            printf($text);
        }
    }
