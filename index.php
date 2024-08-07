<?php
    ini_set('memory_limit', '8096M');
    $GLOBALS['date_telemetria'] = explode('/', '10/06/2024');

    require_once __DIR__ . "/bootstrap.php";

    use Models\User;
    use Process\Telemetry;
    use Process\Logger;

    /**
     * Obtém todos os usuários e seus veículos
     *
     * Extração diária das informações:
     * -> Veículos que se moveram
     * -> Veículos que ligaram mas não se moveram
     * -> Veículos que não ligaram
     * -> Veículos e sua distância percorrida
     *
     */
    $GLOBALS['date_telemetria_carbon'] = \Carbon\Carbon::createFromDate(
        $GLOBALS['date_telemetria'][2], $GLOBALS['date_telemetria'][1], $GLOBALS['date_telemetria'][0]
    );


    /**
     * Criação das tabelas de telemetria
     * E extração das informações de cada usuário
     */
    // $users = User::all();
	try {
    	$Logger = new Logger(__DIR__);
    	$Init = \Carbon\Carbon::now("America/Sao_Paulo");
    	$Logger->save("Telemetry", "\n[SCRIPT DIÁRIO INICIOU EM: {$Init->format("d/m/y H:i:s")}] \n");
    	$client_id = $argv[1];
    	$user = User::where('id', $client_id)->firstOrFail();
    	$Telemetry = new Telemetry($user, $Logger);
        if( is_null($Telemetry->create($Eloquent)) ) {
            $Telemetry->calculate();
        }
        $Telemetry = null;
        sleep(1);
    } catch (Exception $e) {
    	echo "Error log";
    	$Logger->save("Telemetry", "Error: {$e->getMessage()}");
    } finally {
    	echo "\n";
    	$Final = \Carbon\Carbon::now("America/Sao_Paulo");
    	$Logger->save("Telemetry", "[MEMORY TOTAL ALOCADA: " . memory_get_usage(true) . "] \n");
    	$Logger->save("Telemetry", "[SCRIPT DIÁRIO TERMINOU EM: {$Final->format("d/m/y H:i:s")}] \n");
    }
