<?php
    ini_set('memory_limit', '8096M');

    require_once __DIR__ . "/bootstrap.php";

    use Models\User;
    use Models\Sincronizacao;
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


    /**
     * Criação das tabelas de telemetria
     * E extração das informações de cada usuário
     */
    // $users = User::all();
    $Date_to_sync = 'undefined';
	try {
        $Today = \Carbon\Carbon::now("America/Sao_Paulo");
    	$Logger = new Logger(__DIR__, $Today->format('Y-m-d'));
        
        // verify user exist
    	$client_id = $argv[1];
        $GLOBALS['client_id'] = $client_id;
    	$user = User::findOrFail($client_id);
        
        // Log init
    	$Logger->save("Telemetry_".$client_id, "\n[SCRIPT DIÁRIO INICIOU EM: {$Today->format("d/m/y H:i:s")}] \n");
        $Logger->save("Telemetry_".$client_id, "\n[CLIENTE ID: {$client_id}] \n");

        // verify sync exist
        $sync = new Sincronizacao();
        $sync = $sync->running($client_id);
        $sync->status = Sincronizacao::STATUS_RUN;
        $sync->saveOrFail();

        // calculate distances and replace telemetria
    	$Telemetry = new Telemetry($Eloquent, $user, $Logger);
        if( is_null($Telemetry->create()) ) {
            $Logger->save("Telemetry_".$client_id, "##### Init Synchronize ID: {$sync->id}\n");
            // calculate saved
            for ($i=0; $i < 2; $i++) {
                $Date = $Today->subDays(1)->format('Y-m-d');
                $Logger->save("Telemetry_".$client_id, "\n\n##-> Start TelemetryCalculate to DATE = {$Date}\n");
                if (!$Telemetry->calculate($Date)) {
                    $sync->status = Sincronizacao::STATUS_FAIL;
                    $Logger->save("Telemetry_".$client_id, "\CalculateRetnFalse: {$Date}");
                    break;
                } else {
                    $sync->status = Sincronizacao::STATUS_FINISH;
                }
            }
            $sync->finished_at = \Carbon\Carbon::now("America/Sao_Paulo");
            $sync->saveOrFail();
        }
        unset($Telemetry);
        sleep(1);
    } catch (Exception $e) {
        $Logger->save("Telemetry_".$client_id, "\nExceptionErrorDate: {$Date_to_sync}");
    	$Logger->save("Telemetry_".$client_id, "\nExceptionErrorIndex: {$e->getMessage()}");
    } finally {
    	echo "\n";
    	$Final = \Carbon\Carbon::now("America/Sao_Paulo");
    	$Logger->save("Telemetry_".$client_id, "\n[MEMORY TOTAL ALOCADA: " . memory_get_usage(true) . "]");
    	$Logger->save("Telemetry_".$client_id, "\n[SCRIPT DIÁRIO TERMINOU EM: {$Final->format("d/m/y H:i:s")}] \n");
    }
