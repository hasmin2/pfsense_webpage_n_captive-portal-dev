<?php

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

global $config;

/*
 * gateways 배열이 없으면 생성
 */
if (!isset($config['gateways']) || !is_array($config['gateways'])) {
    $config['gateways'] = array();
}

$hasTimestamp = isset($config['gateways']['manualroutetimestamp']);
$hasDuration  = isset($config['gateways']['manualrouteduration']);

$changed = false;

/*
 * manualroutetimestamp + manualrouteduration 둘 다 있는 경우
 */
if ($hasTimestamp && $hasDuration) {

    $manualRouteTimestamp = $config['gateways']['manualroutetimestamp'];
    $manualRouteDuration  = $config['gateways']['manualrouteduration'];

    /*
     * 값이 숫자가 아니면 설정 이상으로 보고 자동 복구
     */
    if (!is_numeric($manualRouteTimestamp) || !is_numeric($manualRouteDuration)) {

        unset($config['gateways']['manualroutetimestamp']);
        unset($config['gateways']['manualrouteduration']);

        $changed = true;

        echo "uncecessary setting for time duration, recovering back to auto-routing";

    } else {

        $date = new DateTime();

        /*
         * 기존 코드와 동일하게 분 단위 timestamp 비교
         */
        $currentMinuteTimestamp = round($date->getTimestamp() / 60, 0);
        $elapsedMinutes = $currentMinuteTimestamp - (float)$manualRouteTimestamp;

        if ($elapsedMinutes >= (float)$manualRouteDuration) {

            unset($config['gateways']['manualroutetimestamp']);
            unset($config['gateways']['manualrouteduration']);

            $changed = true;

            echo "back to auto routing due to duration is expire\n";

        } else {
            echo "still manual routing activated";
        }
    }

    /*
     * 둘 다 없는 경우
     */
} elseif (!$hasTimestamp && !$hasDuration) {

    echo "auto routing enabled, no action performed.";

    /*
     * 둘 중 하나만 있는 비정상 상태
     */
} else {

    if ($hasTimestamp && !$hasDuration) {
        unset($config['gateways']['manualroutetimestamp']);
        $changed = true;
    } elseif (!$hasTimestamp && $hasDuration) {
        unset($config['gateways']['manualrouteduration']);
        $changed = true;
    }

    echo "uncecessary setting for time duration, recovering back to auto-routing";
}

/*
 * 변경이 있을 때만 config 저장
 */
if ($changed) {
    sleep(2);
    write_config("Modified gateway via API");
}

?>