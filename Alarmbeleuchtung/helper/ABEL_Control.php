<?php

/**
 * @project       Alarmbeleuchtung/Alarmbeleuchtung
 * @file          ABEL_Control.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpVoidFunctionResultUsedInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait ABEL_Control
{
    /**
     * Toggles the alarm light off or on.
     *
     * @param bool $State
     * false =  off
     * true =   on
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     *
     * @throws Exception
     */
    public function ToggleAlarmLight(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $statusText = 'Aus';
        $value = 'false';
        if ($State) {
            $statusText = 'An';
            $value = 'true';
        }
        $this->SendDebug(__FUNCTION__, 'Status: ' . $statusText, 0);
        if ($State) {
            if ($this->CheckMaintenance()) {
                return false;
            }
        }
        $result = false;
        $id = $this->ReadPropertyInteger('AlarmLight');
        if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
            $result = true;
            $timestamp = date('d.m.Y, H:i:s');
            $location = $this->ReadPropertyString('Location');
            $actualAlarmLightState = $this->GetValue('AlarmLight');
            //Deactivate
            if (!$State) {
                $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wird ausgeschaltet', 0);
                $this->SetTimerInterval('ActivateAlarmLight', 0);
                $this->SetTimerInterval('DeactivateAlarmLight', 0);
                $this->SetValue('AlarmLight', false);
                $commandControl = $this->ReadPropertyInteger('CommandControl');
                if ($commandControl > 1 && @IPS_ObjectExists($commandControl)) { //0 = main category, 1 = none
                    $commands = [];
                    $commands[] = '@RequestAction(' . $id . ', ' . $value . ');';
                    $this->SendDebug(__FUNCTION__, 'Befehl: ' . json_encode(json_encode($commands)), 0);
                    $scriptText = self::ABLAUFSTEUERUNG_MODULE_PREFIX . '_ExecuteCommands(' . $commandControl . ', ' . json_encode(json_encode($commands)) . ');';
                    $this->SendDebug(__FUNCTION__, 'Ablaufsteuerung: ' . $scriptText, 0);
                    $result = @IPS_RunScriptText($scriptText);
                } else {
                    IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
                    //Enter semaphore
                    if (!$this->LockSemaphore('ToggleAlarmLight')) {
                        $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore wurde erreicht!', 0);
                        $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore wurde erreicht!', KL_WARNING);
                        $this->UnlockSemaphore('ToggleAlarmLight');
                        //Revert
                        $this->SetValue('AlarmLight', $actualAlarmLightState);
                        $text = 'Fehler, die Alarmbeleuchtung konnte nicht ausgeschaltet werden!';
                        $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                        //Protocol
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmbeleuchtung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                        return false;
                    }
                    $response = @RequestAction($id, false);
                    if (!$response) {
                        IPS_Sleep(self::DELAY_MILLISECONDS);
                        $response = @RequestAction($id, false);
                        if (!$response) {
                            IPS_Sleep(self::DELAY_MILLISECONDS * 2);
                            $response = @RequestAction($id, false);
                            if (!$response) {
                                $result = false;
                            }
                        }
                    }
                    //Leave semaphore
                    $this->UnlockSemaphore('ToggleAlarmLight');
                }
                if ($result) {
                    //Protocol
                    if ($actualAlarmLightState) {
                        $text = 'Die Alarmbeleuchtung wurde ausgeschaltet';
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmbeleuchtung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                    }
                } else {
                    //Revert on failure
                    $this->SetValue('AlarmLight', $actualAlarmLightState);
                    //Log
                    $text = 'Fehler, die Alarmbeleuchtung konnte nicht ausgeschaltet werden!';
                    $this->SendDebug(__FUNCTION__, $text, 0);
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                    //Protocol
                    if ($actualAlarmLightState) {
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmbeleuchtung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                    }
                }
            }
            //Activate
            if ($State) {
                //Delay
                $delay = $this->ReadPropertyInteger('AlarmLightSwitchOnDelay');
                if ($delay > 0) {
                    $this->SetTimerInterval('ActivateAlarmLight', $delay * 1000);
                    $unit = 'Sekunden';
                    if ($delay == 1) {
                        $unit = 'Sekunde';
                    }
                    $this->SetValue('AlarmLight', true);
                    $text = 'Die Alarmbeleuchtung wird in ' . $delay . ' ' . $unit . ' eingeschaltet';
                    $this->SendDebug(__FUNCTION__, $text, 0);
                    if (!$actualAlarmLightState) {
                        //Protocol
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmbeleuchtung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                    }
                } // No delay, activate alarm light immediately
                else {
                    if (!$actualAlarmLightState) {
                        $result = $this->ActivateAlarmLight();
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Activates the alarm light.
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     */
    public function ActivateAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetTimerInterval('ActivateAlarmLight', 0);
        if ($this->CheckMaintenance()) {
            return false;
        }
        return $this->TriggerAlarmLight();
    }

    /**
     * Deactivates the alarm light.
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     *
     * @throws Exception
     */
    public function DeactivateAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetTimerInterval('DeactivateAlarmLight', 0);
        if ($this->CheckMaintenance()) {
            return false;
        }
        return $this->ToggleAlarmLight(false);
    }

    public function TriggerAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if ($this->CheckMaintenance()) {
            return false;
        }
        $result = false;
        $id = $this->ReadPropertyInteger('AlarmLight');
        if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
            $result = true;
            $timestamp = date('d.m.Y, H:i:s');
            $location = $this->ReadPropertyString('Location');
            $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wird eingeschaltet', 0);
            $this->SetTimerInterval('ActivateAlarmLight', 0);
            $actualAlarmLightState = $this->GetValue('AlarmLight');
            $this->SetValue('AlarmLight', true);
            $commandControl = $this->ReadPropertyInteger('CommandControl');
            if ($commandControl > 1 && @IPS_ObjectExists($commandControl)) { //0 = main category, 1 = none
                $commands = [];
                $commands[] = '@RequestAction(' . $id . ', ' . true . ');';
                $this->SendDebug(__FUNCTION__, 'Befehl: ' . json_encode(json_encode($commands)), 0);
                $scriptText = self::ABLAUFSTEUERUNG_MODULE_PREFIX . '_ExecuteCommands(' . $commandControl . ', ' . json_encode(json_encode($commands)) . ');';
                $this->SendDebug(__FUNCTION__, 'Ablaufsteuerung: ' . $scriptText, 0);
                $result = @IPS_RunScriptText($scriptText);
            } else {
                IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
                //Enter semaphore
                if (!$this->LockSemaphore('ToggleAlarmLight')) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore wurde erreicht!', 0);
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore wurde erreicht!', KL_WARNING);
                    $this->UnlockSemaphore('ToggleAlarmLight');
                    $this->SetValue('AlarmLight', $actualAlarmLightState);
                    $text = 'Fehler, die Alarmbeleuchtung konnte nicht eingeschaltet werden!';
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                    //Protocol
                    if ($location == '') {
                        $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                    } else {
                        $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmbeleuchtung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                    }
                    $this->UpdateAlarmProtocol($logText, 0);
                    return false;
                }
                $response = @RequestAction($id, true);
                if (!$response) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $response = @RequestAction($id, true);
                    if (!$response) {
                        IPS_Sleep(self::DELAY_MILLISECONDS * 2);
                        $response = @RequestAction($id, true);
                        if (!$response) {
                            $result = false;
                        }
                    }
                }
                //Leave semaphore
                $this->UnlockSemaphore('ToggleAlarmLight');
            }
            if ($result) {
                //Protocol
                $text = 'Die Alarmbeleuchtung wurde eingeschaltet';
                if ($location == '') {
                    $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                } else {
                    $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmbeleuchtung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                }
                $this->UpdateAlarmProtocol($logText, 0);
                //Switch on duration
                $duration = $this->ReadPropertyInteger('AlarmLightSwitchOnDuration');
                $this->SetTimerInterval('DeactivateAlarmLight', $duration * 60 * 1000);
                if ($duration > 0) {
                    $unit = 'Minuten';
                    if ($duration == 1) {
                        $unit = 'Minute';
                    }
                    $this->SendDebug(__FUNCTION__, 'Einschaltdauer, die Alarmbeleuchtung wird in ' . $duration . ' ' . $unit . ' automatisch ausgeschaltet', 0);
                }
            } else {
                //Revert on failure
                $this->SetValue('AlarmLight', $actualAlarmLightState);
                //Log
                $text = 'Fehler, die Alarmbeleuchtung konnte nicht eingeschaltet werden!';
                $this->SendDebug(__FUNCTION__, $text, 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                //Protocol
                if ($location == '') {
                    $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                } else {
                    $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmbeleuchtung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                }
                $this->UpdateAlarmProtocol($logText, 0);
            }
        }
        return $result;
    }

    /**
     * Starts the automatic deactivation.
     */
    public function StartAutomaticDeactivation(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetValue('Active', false);
        //Turn the alarm light off
        $this->ToggleAlarmLight(false);
        $this->SetAutomaticDeactivationTimer();
    }

    /**
     * Stops the automatic deactivation.
     */
    public function StopAutomaticDeactivation(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetValue('Active', true);
        $this->SetAutomaticDeactivationTimer();
    }

    #################### Private

    private function SetAutomaticDeactivationTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $use = $this->ReadPropertyBoolean('UseAutomaticDeactivation');
        //Start
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('AutomaticDeactivationStartTime');
        }
        $this->SetTimerInterval('StartAutomaticDeactivation', $milliseconds);
        //End
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('AutomaticDeactivationEndTime');
        }
        $this->SetTimerInterval('StopAutomaticDeactivation', $milliseconds);
    }

    /**
     * Gets the interval for a timer.
     *
     * @param string $TimerName
     * @return int
     * @throws Exception
     */
    private function GetInterval(string $TimerName): int
    {
        $timer = json_decode($this->ReadPropertyString($TimerName));
        $now = time();
        $hour = $timer->hour;
        $minute = $timer->minute;
        $second = $timer->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        return ($timestamp - $now) * 1000;
    }

    /**
     * Checks the status of the automatic deactivation timer.
     *
     * @return bool
     * @throws Exception
     */
    private function CheckAutomaticDeactivationTimer(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if (!$this->ReadPropertyBoolean('UseAutomaticDeactivation')) {
            return false;
        }
        $start = $this->GetTimerInterval('StartAutomaticDeactivation');
        $stop = $this->GetTimerInterval('StopAutomaticDeactivation');
        if ($start > $stop) {
            //Deactivation timer is active, must be toggled to inactive
            $this->SetValue('Active', false);
            //Turn alarm light off
            $this->ToggleAlarmLight(false);
            return true;
        } else {
            //Deactivation timer is inactive, must be toggled to active
            $this->SetValue('Active', true);
            return false;
        }
    }

    /**
     * Attempts to set the semaphore and repeats this up to multiple times.
     *
     * @param $Name
     * @return bool
     * @throws Exception
     */
    private function LockSemaphore($Name): bool
    {
        $attempts = 1000;
        for ($i = 0; $i < $attempts; $i++) {
            if (IPS_SemaphoreEnter(__CLASS__ . '.' . $this->InstanceID . '.' . $Name, 1)) {
                $this->SendDebug(__FUNCTION__, 'Semaphore ' . $Name . ' locked', 0);
                return true;
            } else {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    /**
     * Leaves the semaphore.
     *
     * @param $Name
     * @return void
     */
    private function UnlockSemaphore($Name): void
    {
        @IPS_SemaphoreLeave(__CLASS__ . '.' . $this->InstanceID . '.' . $Name);
        $this->SendDebug(__FUNCTION__, 'Semaphore ' . $Name . ' unlocked', 0);
    }
}