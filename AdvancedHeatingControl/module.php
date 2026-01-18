<?php
declare(strict_types=1);

class AdvancedHeatingControl extends IPSModule
{
    private const VM_UPDATE = 10603;

    private const ACTION_OFF = 1;
    private const ACTION_COMFORT = 2;
    private const ACTION_ECO = 3;
    private const ACTION_AWAY = 4;
    private const ACTION_BOOST = 5;

    /* ================= Lifecycle ================= */
    public function Create()
    {
        parent::Create();

        // ---- Properties: Thermostats ----
        $this->RegisterPropertyString('Thermostats', '[]');

        // ---- Properties: Temperature Sensors ----
        $this->RegisterPropertyString('TemperatureSensors', '[]');

        // ---- Properties: Window Contacts ----
        $this->RegisterPropertyString('WindowContacts', '[]');

        // ---- Properties: Temperature Range ----
        $this->RegisterPropertyFloat('MinTemperature', 5.0);
        $this->RegisterPropertyFloat('MaxTemperature', 30.0);
        $this->RegisterPropertyFloat('TemperatureStep', 0.5);

        // ---- Attributes ----
        $this->RegisterAttributeInteger('ScheduleEventID', 0);
        $this->RegisterAttributeFloat('TempBeforeWindowOpen', 21.0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Get presentation IDs dynamically
        $sliderPresentationID = $this->getPresentationIDByCaption('Slider');
        $valuePresentationID = $this->getPresentationIDByCaption('Value Presentation');

        // Temperature slider presentation config (reused for all temperature settings)
        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');
        $stepTemp = $this->ReadPropertyFloat('TemperatureStep');
        $tempPresentation = [
            'PRESENTATION' => $sliderPresentationID,
            'ICON' => 'temperature-half',
            'MIN' => $minTemp,
            'MAX' => $maxTemp,
            'STEP_SIZE' => $stepTemp,
            'SUFFIX' => ' °C',
            'DIGITS' => 1,
            'USAGE_TYPE' => 0,
            'GRADIENT_TYPE' => 1
        ];

        // Target Temperature variable (position 1) - Slider presentation for thermostat visualization
        $this->MaintainVariable('TargetTemperature', $this->Translate('Target Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 1, true);
        $this->EnableAction('TargetTemperature');
        $this->initializeVariableDefault('TargetTemperature', 21.0);

        // Current Room Temperature variable (position 2) - Value Presentation (no action)
        $this->MaintainVariable('CurrentTemperature', $this->Translate('Current Temperature'), VARIABLETYPE_FLOAT, [
            'PRESENTATION' => $valuePresentationID,
            'SUFFIX' => ' °C',
            'DIGITS' => 1,
            'USAGE_TYPE' => 1
        ], 2, true);

        // Heating Mode variable (position 3) - Enumeration presentation
        $enumerationPresentationID = $this->getPresentationIDByCaption('Enumeration');
        $heatingModeOptions = json_encode([
            ['Value' => 0, 'Caption' => $this->Translate('Off'), 'IconActive' => true, 'IconValue' => 'Power', 'Color' => 0x9E9E9E],
            ['Value' => 1, 'Caption' => $this->Translate('Comfort'), 'IconActive' => true, 'IconValue' => 'Temperature', 'Color' => 0xFF6B35],
            ['Value' => 2, 'Caption' => $this->Translate('Eco'), 'IconActive' => true, 'IconValue' => 'Leaf', 'Color' => 0x4CAF50],
            ['Value' => 3, 'Caption' => $this->Translate('Away'), 'IconActive' => true, 'IconValue' => 'Motion', 'Color' => 0x03A9F4],
            ['Value' => 4, 'Caption' => $this->Translate('Boost'), 'IconActive' => true, 'IconValue' => 'Flame', 'Color' => 0xF44336]
        ]);
        $this->MaintainVariable('HeatingMode', $this->Translate('Heating Mode'), VARIABLETYPE_INTEGER, [
            'PRESENTATION' => $enumerationPresentationID,
            'ICON' => 'Temperature',
            'OPTIONS' => $heatingModeOptions
        ], 3, true);
        $this->EnableAction('HeatingMode');
        $this->initializeVariableDefault('HeatingMode', 0);

        // Comfort Temperature variable (position 4)
        $this->MaintainVariable('ComfortTemp', $this->Translate('Comfort Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 4, true);
        $this->EnableAction('ComfortTemp');
        $this->initializeVariableDefault('ComfortTemp', 21.0);

        // Eco Temperature variable (position 5)
        $this->MaintainVariable('EcoTemp', $this->Translate('Eco Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 5, true);
        $this->EnableAction('EcoTemp');
        $this->initializeVariableDefault('EcoTemp', 18.0);

        // Away Temperature variable (position 6)
        $this->MaintainVariable('AwayTemp', $this->Translate('Away Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 6, true);
        $this->EnableAction('AwayTemp');
        $this->initializeVariableDefault('AwayTemp', 15.0);

        // Boost Temperature variable (position 7)
        $this->MaintainVariable('BoostTemp', $this->Translate('Boost Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 7, true);
        $this->EnableAction('BoostTemp');
        $this->initializeVariableDefault('BoostTemp', 24.0);

        // Switch presentation config (reused for boolean switches)
        $switchPresentationID = $this->getPresentationIDByCaption('Switch');
        $switchPresentation = [
            'PRESENTATION' => $switchPresentationID,
            'ICON_ON' => 'Power',
            'ICON_OFF' => 'Power',
            'COLOR_ON' => 0x4CAF50,
            'COLOR_OFF' => 0x9E9E9E
        ];

        // Night Setback Active variable (position 8)
        $this->MaintainVariable('NightSetbackActive', $this->Translate('Night Setback Active'), VARIABLETYPE_BOOLEAN, $switchPresentation, 8, true);
        $this->EnableAction('NightSetbackActive');
        $this->initializeVariableDefault('NightSetbackActive', false);

        // Night Setback Temperature variable (position 9)
        $this->MaintainVariable('NightSetbackTemp', $this->Translate('Night Setback Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 9, true);
        $this->EnableAction('NightSetbackTemp');
        $this->initializeVariableDefault('NightSetbackTemp', 16.0);

        // Manual Operation Blocked variable (position 10) - Switch with lock icon
        $lockSwitchPresentation = [
            'PRESENTATION' => $switchPresentationID,
            'ICON_ON' => 'Lock',
            'ICON_OFF' => 'LockOpen',
            'COLOR_ON' => 0xF44336,
            'COLOR_OFF' => 0x9E9E9E
        ];
        $this->MaintainVariable('ManualOperationBlocked', $this->Translate('Manual Operation Blocked'), VARIABLETYPE_BOOLEAN, $lockSwitchPresentation, 10, true);
        $this->EnableAction('ManualOperationBlocked');
        $this->initializeVariableDefault('ManualOperationBlocked', false);

        // Window Open indicator variable (position 11) - read-only value presentation
        $windowOpenOptions = json_encode([
            ['Value' => false, 'Caption' => $this->Translate('Closed'), 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 0x4CAF50],
            ['Value' => true, 'Caption' => $this->Translate('Open'), 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 0x03A9F4]
        ]);
        $this->MaintainVariable('WindowOpen', $this->Translate('Window Open'), VARIABLETYPE_BOOLEAN, [
            'PRESENTATION' => $valuePresentationID,
            'ICON' => 'Window',
            'OPTIONS' => $windowOpenOptions
        ], 11, true);
        $this->initializeVariableDefault('WindowOpen', false);

        // Window Open Temperature variable (position 12)
        $this->MaintainVariable('WindowOpenTemp', $this->Translate('Window Open Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 12, true);
        $this->EnableAction('WindowOpenTemp');
        $this->initializeVariableDefault('WindowOpenTemp', 12.0);

        // Remove old variables if they exist
        $this->MaintainVariable('FrostTemp', '', VARIABLETYPE_FLOAT, '', 0, false);
        $this->MaintainVariable('HeatingActive', '', VARIABLETYPE_BOOLEAN, '', 0, false);
        $this->MaintainVariable('NightTemp', '', VARIABLETYPE_FLOAT, '', 0, false);

        // Create or update the weekly schedule event
        $this->maintainScheduleEvent();

        // Register message subscriptions
        $this->registerMessages();

        // Update status
        $thermostats = $this->getThermostats();
        if (empty($thermostats)) {
            $this->SetStatus(104); // No thermostats configured
        } else {
            $this->SetStatus(102); // Active
        }

        // Initial temperature update
        $this->updateCurrentTemperature();
    }

    public function Destroy()
    {
        // Clean up schedule event if this instance is being deleted
        $scheduleID = $this->ReadAttributeInteger('ScheduleEventID');
        if ($scheduleID > 0 && @IPS_EventExists($scheduleID)) {
            IPS_DeleteEvent($scheduleID);
        }

        parent::Destroy();
    }

    /* ================= Configuration Form ================= */
    public function GetConfigurationForm(): string
    {
        $scheduleID = $this->ReadAttributeInteger('ScheduleEventID');

        return json_encode([
            'elements' => [
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Thermostats',
                    'expanded' => true,
                    'items' => [
                        [
                            'type' => 'List',
                            'name' => 'Thermostats',
                            'caption' => 'Heating Thermostats',
                            'rowCount' => 5,
                            'add' => true,
                            'delete' => true,
                            'columns' => [
                                [
                                    'caption' => 'Thermostat Variable',
                                    'name' => 'ThermostatID',
                                    'width' => '400px',
                                    'add' => 0,
                                    'edit' => [
                                        'type' => 'SelectVariable',
                                        'validVariableTypes' => [VARIABLETYPE_FLOAT, VARIABLETYPE_INTEGER]
                                    ]
                                ],
                                [
                                    'caption' => 'Name',
                                    'name' => 'Name',
                                    'width' => 'auto',
                                    'add' => '',
                                    'edit' => [
                                        'type' => 'ValidationTextBox'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Temperature Sensors',
                    'items' => [
                        [
                            'type' => 'List',
                            'name' => 'TemperatureSensors',
                            'caption' => 'Room Temperature Sensors',
                            'rowCount' => 5,
                            'add' => true,
                            'delete' => true,
                            'columns' => [
                                [
                                    'caption' => 'Sensor Variable',
                                    'name' => 'SensorID',
                                    'width' => '400px',
                                    'add' => 0,
                                    'edit' => [
                                        'type' => 'SelectVariable',
                                        'validVariableTypes' => [VARIABLETYPE_FLOAT, VARIABLETYPE_INTEGER]
                                    ]
                                ],
                                [
                                    'caption' => 'Name',
                                    'name' => 'Name',
                                    'width' => 'auto',
                                    'add' => '',
                                    'edit' => [
                                        'type' => 'ValidationTextBox'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Window Contacts',
                    'items' => [
                        [
                            'type' => 'List',
                            'name' => 'WindowContacts',
                            'caption' => 'Window Contact Sensors',
                            'rowCount' => 5,
                            'add' => true,
                            'delete' => true,
                            'columns' => [
                                [
                                    'caption' => 'Contact Variable',
                                    'name' => 'ContactID',
                                    'width' => '400px',
                                    'add' => 0,
                                    'edit' => [
                                        'type' => 'SelectVariable',
                                        'validVariableTypes' => [VARIABLETYPE_BOOLEAN]
                                    ]
                                ],
                                [
                                    'caption' => 'Name',
                                    'name' => 'Name',
                                    'width' => 'auto',
                                    'add' => '',
                                    'edit' => [
                                        'type' => 'ValidationTextBox'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Temperature Settings',
                    'items' => [
                        [
                            'type' => 'NumberSpinner',
                            'name' => 'MinTemperature',
                            'caption' => 'Minimum Temperature',
                            'suffix' => '°C',
                            'digits' => 1,
                            'minimum' => 0.0,
                            'maximum' => 20.0
                        ],
                        [
                            'type' => 'NumberSpinner',
                            'name' => 'MaxTemperature',
                            'caption' => 'Maximum Temperature',
                            'suffix' => '°C',
                            'digits' => 1,
                            'minimum' => 15.0,
                            'maximum' => 35.0
                        ],
                        [
                            'type' => 'NumberSpinner',
                            'name' => 'TemperatureStep',
                            'caption' => 'Temperature Step',
                            'suffix' => '°C',
                            'digits' => 1,
                            'minimum' => 0.1,
                            'maximum' => 1.0
                        ]
                    ]
                ],
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Weekly Schedule',
                    'items' => [
                        [
                            'type' => 'Label',
                            'caption' => 'The weekly schedule is managed via IP-Symcon\'s built-in schedule event.'
                        ],
                        [
                            'type' => 'Label',
                            'caption' => 'Configure Comfort, Eco, Away, Boost, and Off times in the schedule below the instance.'
                        ],
                        [
                            'type' => 'OpenObjectButton',
                            'objectID' => $scheduleID,
                            'caption' => 'Open Weekly Schedule'
                        ]
                    ]
                ]
            ],
            'status' => [
                [
                    'code' => 102,
                    'icon' => 'active',
                    'caption' => 'Module is active'
                ],
                [
                    'code' => 104,
                    'icon' => 'inactive',
                    'caption' => 'No thermostats configured'
                ]
            ]
        ]);
    }

    /* ================= Action Handling ================= */
    public function RequestAction($Ident, $Value)
    {
        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');

        switch ($Ident) {
            case 'TargetTemperature':
                $val = max($minTemp, min($maxTemp, (float)$Value));
                SetValue($this->GetIDForIdent('TargetTemperature'), $val);
                $this->ApplyTemperature();
                break;

            case 'HeatingMode':
                $mode = (int)$Value;
                SetValue($this->GetIDForIdent('HeatingMode'), $mode);
                // Only apply if window is not open
                if (!$this->isWindowOpen()) {
                    $this->applyHeatingMode($mode);
                }
                break;

            case 'ComfortTemp':
                $val = max($minTemp, min($maxTemp, (float)$Value));
                SetValue($this->GetIDForIdent('ComfortTemp'), $val);
                // Update target if currently in comfort mode and window not open
                if ($this->getCurrentMode() === 1 && !$this->isWindowOpen()) {
                    SetValue($this->GetIDForIdent('TargetTemperature'), $val);
                    $this->ApplyTemperature();
                }
                break;

            case 'EcoTemp':
                $val = max($minTemp, min($maxTemp, (float)$Value));
                SetValue($this->GetIDForIdent('EcoTemp'), $val);
                // Update target if currently in eco mode and window not open
                if ($this->getCurrentMode() === 2 && !$this->isWindowOpen()) {
                    SetValue($this->GetIDForIdent('TargetTemperature'), $val);
                    $this->ApplyTemperature();
                }
                break;

            case 'AwayTemp':
                $val = max($minTemp, min($maxTemp, (float)$Value));
                SetValue($this->GetIDForIdent('AwayTemp'), $val);
                // Update target if currently in away mode and neither night setback nor window active
                if ($this->getCurrentMode() === 3 && !$this->isNightSetbackActive() && !$this->isWindowOpen()) {
                    SetValue($this->GetIDForIdent('TargetTemperature'), $val);
                    $this->ApplyTemperature();
                }
                break;

            case 'BoostTemp':
                $val = max($minTemp, min($maxTemp, (float)$Value));
                SetValue($this->GetIDForIdent('BoostTemp'), $val);
                // Update target if currently in boost mode and neither night setback nor window active
                if ($this->getCurrentMode() === 4 && !$this->isNightSetbackActive() && !$this->isWindowOpen()) {
                    SetValue($this->GetIDForIdent('TargetTemperature'), $val);
                    $this->ApplyTemperature();
                }
                break;

            case 'NightSetbackActive':
                $active = (bool)$Value;
                SetValue($this->GetIDForIdent('NightSetbackActive'), $active);
                // Only apply if window is not open
                if (!$this->isWindowOpen()) {
                    if ($active) {
                        // Night setback activated - apply setback temperature
                        $setbackTemp = @GetValue($this->GetIDForIdent('NightSetbackTemp'));
                        SetValue($this->GetIDForIdent('TargetTemperature'), $setbackTemp);
                        $this->ApplyTemperature();
                    } else {
                        // Night setback deactivated - reapply current schedule mode
                        $this->applyHeatingMode($this->getCurrentMode());
                    }
                }
                break;

            case 'NightSetbackTemp':
                $val = max($minTemp, min($maxTemp, (float)$Value));
                SetValue($this->GetIDForIdent('NightSetbackTemp'), $val);
                // Update target if night setback is currently active and window not open
                if ($this->isNightSetbackActive() && !$this->isWindowOpen()) {
                    SetValue($this->GetIDForIdent('TargetTemperature'), $val);
                    $this->ApplyTemperature();
                }
                break;

            case 'ManualOperationBlocked':
                SetValue($this->GetIDForIdent('ManualOperationBlocked'), (bool)$Value);
                break;

            case 'WindowOpenTemp':
                $val = max($minTemp, min($maxTemp, (float)$Value));
                SetValue($this->GetIDForIdent('WindowOpenTemp'), $val);
                // Update target if window is currently open
                if ($this->isWindowOpen()) {
                    SetValue($this->GetIDForIdent('TargetTemperature'), $val);
                    $this->ApplyTemperature();
                }
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    /* ================= Schedule Action Handler ================= */
    /**
     * Called by the weekly schedule event when action changes
     * @param int $ActionID The action ID from the schedule
     */
    public function ScheduleAction(int $ActionID): void
    {
        // Determine the mode from action ID (mode order: Off=0, Comfort=1, Eco=2, Away=3, Boost=4)
        $mode = 0;
        switch ($ActionID) {
            case self::ACTION_OFF:
                $mode = 0;
                break;
            case self::ACTION_COMFORT:
                $mode = 1;
                break;
            case self::ACTION_ECO:
                $mode = 2;
                break;
            case self::ACTION_AWAY:
                $mode = 3;
                break;
            case self::ACTION_BOOST:
                $mode = 4;
                break;
        }

        // Always update the heating mode variable (so we know what schedule wants)
        SetValue($this->GetIDForIdent('HeatingMode'), $mode);

        // Only apply temperature to thermostats if night setback and window are NOT active
        if (!$this->isNightSetbackActive() && !$this->isWindowOpen()) {
            $this->applyHeatingMode($mode);
        }
    }

    /* ================= Message Sink ================= */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message !== self::VM_UPDATE) {
            return;
        }

        // Check if sender is a temperature sensor
        $sensors = $this->getTemperatureSensors();
        foreach ($sensors as $sensor) {
            if ((int)$sensor['SensorID'] === $SenderID) {
                $this->updateCurrentTemperature();
                return;
            }
        }

        // Check if sender is a thermostat
        $thermostats = $this->getThermostats();
        foreach ($thermostats as $thermostat) {
            if ((int)$thermostat['ThermostatID'] === $SenderID) {
                $this->handleThermostatChange($SenderID, (float)$Data[0]);
                return;
            }
        }

        // Check if sender is a window contact
        $contacts = $this->getWindowContacts();
        foreach ($contacts as $contact) {
            if ((int)$contact['ContactID'] === $SenderID) {
                $this->handleWindowContactChange();
                return;
            }
        }
    }

    /* ================= Public Functions ================= */

    /**
     * Set the target temperature for all thermostats
     * @param float $Temperature Target temperature in °C
     */
    public function SetTargetTemperature(float $Temperature): void
    {
        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');
        $temp = max($minTemp, min($maxTemp, $Temperature));
        SetValue($this->GetIDForIdent('TargetTemperature'), $temp);
        $this->ApplyTemperature();
    }

    /**
     * Apply the current target temperature to all thermostats
     */
    public function ApplyTemperature(): void
    {
        $targetTemp = @GetValue($this->GetIDForIdent('TargetTemperature'));
        $thermostats = $this->getThermostats();

        foreach ($thermostats as $thermostat) {
            $thermostatID = (int)$thermostat['ThermostatID'];
            if ($thermostatID > 0 && @IPS_VariableExists($thermostatID)) {
                $current = @GetValue($thermostatID);
                if (abs($current - $targetTemp) > 0.05) {
                    @RequestAction($thermostatID, $targetTemp);
                }
            }
        }
    }

    /**
     * Set the heating mode
     * @param int $Mode 0=Off, 1=Comfort, 2=Eco, 3=Away, 4=Boost
     */
    public function SetHeatingMode(int $Mode): void
    {
        $mode = max(0, min(4, $Mode));
        SetValue($this->GetIDForIdent('HeatingMode'), $mode);
        // Only apply if night setback is not active
        if (!$this->isNightSetbackActive()) {
            $this->applyHeatingMode($mode);
        }
    }

    /**
     * Turn heating off
     */
    public function SetOff(): void
    {
        $this->SetHeatingMode(0);
    }

    /**
     * Set to comfort mode
     */
    public function SetComfortMode(): void
    {
        $this->SetHeatingMode(1);
    }

    /**
     * Set to eco mode
     */
    public function SetEcoMode(): void
    {
        $this->SetHeatingMode(2);
    }

    /**
     * Set to away mode
     */
    public function SetAwayMode(): void
    {
        $this->SetHeatingMode(3);
    }

    /**
     * Set to boost mode
     */
    public function SetBoostMode(): void
    {
        $this->SetHeatingMode(4);
    }

    /**
     * Enable or disable night setback
     * @param bool $Active True to enable, false to disable
     */
    public function SetNightSetback(bool $Active): void
    {
        SetValue($this->GetIDForIdent('NightSetbackActive'), $Active);
        if ($Active) {
            // Night setback activated - apply setback temperature
            $setbackTemp = @GetValue($this->GetIDForIdent('NightSetbackTemp'));
            SetValue($this->GetIDForIdent('TargetTemperature'), $setbackTemp);
            $this->ApplyTemperature();
        } else {
            // Night setback deactivated - reapply current schedule mode
            $this->applyHeatingMode($this->getCurrentMode());
        }
    }

    /**
     * Get the current room temperature (average of all sensors)
     * @return float Current temperature in °C
     */
    public function GetCurrentTemperature(): float
    {
        return (float)@GetValue($this->GetIDForIdent('CurrentTemperature'));
    }

    /**
     * Get the current target temperature
     * @return float Target temperature in °C
     */
    public function GetTargetTemperature(): float
    {
        return (float)@GetValue($this->GetIDForIdent('TargetTemperature'));
    }

    /**
     * Get the schedule event ID
     * @return int Event ID or 0 if not exists
     */
    public function GetScheduleEventID(): int
    {
        return $this->ReadAttributeInteger('ScheduleEventID');
    }

    /* ================= Private Helper Functions ================= */

    private function getThermostats(): array
    {
        $raw = @json_decode($this->ReadPropertyString('Thermostats'), true);
        if (!is_array($raw)) {
            return [];
        }
        return array_filter($raw, function ($thermostat) {
            $id = (int)($thermostat['ThermostatID'] ?? 0);
            return $id > 0 && @IPS_VariableExists($id);
        });
    }

    private function getTemperatureSensors(): array
    {
        $raw = @json_decode($this->ReadPropertyString('TemperatureSensors'), true);
        if (!is_array($raw)) {
            return [];
        }
        return array_filter($raw, function ($sensor) {
            $id = (int)($sensor['SensorID'] ?? 0);
            return $id > 0 && @IPS_VariableExists($id);
        });
    }

    private function getWindowContacts(): array
    {
        $raw = @json_decode($this->ReadPropertyString('WindowContacts'), true);
        if (!is_array($raw)) {
            return [];
        }
        return array_filter($raw, function ($contact) {
            $id = (int)($contact['ContactID'] ?? 0);
            return $id > 0 && @IPS_VariableExists($id);
        });
    }

    private function registerMessages(): void
    {
        // Unregister all previous
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Register thermostat variables
        $thermostats = $this->getThermostats();
        foreach ($thermostats as $thermostat) {
            $thermostatID = (int)$thermostat['ThermostatID'];
            if ($thermostatID > 0) {
                $this->RegisterMessage($thermostatID, self::VM_UPDATE);
            }
        }

        // Register temperature sensor variables
        $sensors = $this->getTemperatureSensors();
        foreach ($sensors as $sensor) {
            $sensorID = (int)$sensor['SensorID'];
            if ($sensorID > 0) {
                $this->RegisterMessage($sensorID, self::VM_UPDATE);
            }
        }

        // Register window contact variables
        $contacts = $this->getWindowContacts();
        foreach ($contacts as $contact) {
            $contactID = (int)$contact['ContactID'];
            if ($contactID > 0) {
                $this->RegisterMessage($contactID, self::VM_UPDATE);
            }
        }
    }

    private function updateCurrentTemperature(): void
    {
        $sensors = $this->getTemperatureSensors();
        if (empty($sensors)) {
            return;
        }

        $total = 0.0;
        $count = 0;

        foreach ($sensors as $sensor) {
            $sensorID = (int)$sensor['SensorID'];
            if ($sensorID > 0 && @IPS_VariableExists($sensorID)) {
                $total += (float)@GetValue($sensorID);
                $count++;
            }
        }

        if ($count > 0) {
            $average = round($total / $count, 1);
            @SetValue($this->GetIDForIdent('CurrentTemperature'), $average);
        }
    }

    private function getCurrentMode(): int
    {
        $modeID = @$this->GetIDForIdent('HeatingMode');
        if ($modeID && @IPS_VariableExists($modeID)) {
            return (int)@GetValue($modeID);
        }
        return 0; // Default: Off
    }

    private function isNightSetbackActive(): bool
    {
        $varID = @$this->GetIDForIdent('NightSetbackActive');
        if ($varID && @IPS_VariableExists($varID)) {
            return (bool)@GetValue($varID);
        }
        return false;
    }

    private function applyHeatingMode(int $mode): void
    {
        $targetTemp = 0.0;

        switch ($mode) {
            case 0: // Off - use minimum temperature
                $targetTemp = $this->ReadPropertyFloat('MinTemperature');
                break;

            case 1: // Comfort
                $targetTemp = @GetValue($this->GetIDForIdent('ComfortTemp'));
                break;

            case 2: // Eco
                $targetTemp = @GetValue($this->GetIDForIdent('EcoTemp'));
                break;

            case 3: // Away
                $targetTemp = @GetValue($this->GetIDForIdent('AwayTemp'));
                break;

            case 4: // Boost
                $targetTemp = @GetValue($this->GetIDForIdent('BoostTemp'));
                break;
        }

        SetValue($this->GetIDForIdent('TargetTemperature'), $targetTemp);
        $this->ApplyTemperature();
    }

    private function handleThermostatChange(int $thermostatID, float $newTemp): void
    {
        $currentTarget = @GetValue($this->GetIDForIdent('TargetTemperature'));
        
        // Check if the change was significant (not just our own update)
        if (abs($newTemp - $currentTarget) < 0.05) {
            return;
        }

        // Check if window is open - block all manual changes
        if ($this->isWindowOpen()) {
            // Immediately revert the thermostat to our target temperature
            @RequestAction($thermostatID, $currentTarget);
            return;
        }

        // Check if manual operation is blocked
        if ($this->isManualOperationBlocked()) {
            // Immediately revert the thermostat to our target temperature
            @RequestAction($thermostatID, $currentTarget);
            return;
        }

        // Manual operation allowed - sync the new temperature
        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');
        $newTemp = max($minTemp, min($maxTemp, $newTemp));

        // Update target temperature variable
        SetValue($this->GetIDForIdent('TargetTemperature'), $newTemp);

        // Sync to all other thermostats
        $thermostats = $this->getThermostats();
        foreach ($thermostats as $thermostat) {
            $id = (int)$thermostat['ThermostatID'];
            if ($id > 0 && $id !== $thermostatID && @IPS_VariableExists($id)) {
                $current = @GetValue($id);
                if (abs($current - $newTemp) > 0.05) {
                    @RequestAction($id, $newTemp);
                }
            }
        }
    }

    private function isManualOperationBlocked(): bool
    {
        $varID = @$this->GetIDForIdent('ManualOperationBlocked');
        if ($varID && @IPS_VariableExists($varID)) {
            return (bool)@GetValue($varID);
        }
        return false;
    }

    private function isWindowOpen(): bool
    {
        $varID = @$this->GetIDForIdent('WindowOpen');
        if ($varID && @IPS_VariableExists($varID)) {
            return (bool)@GetValue($varID);
        }
        return false;
    }

    private function checkAnyWindowOpen(): bool
    {
        $contacts = $this->getWindowContacts();
        foreach ($contacts as $contact) {
            $contactID = (int)$contact['ContactID'];
            if ($contactID > 0 && @IPS_VariableExists($contactID)) {
                if ((bool)@GetValue($contactID)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function handleWindowContactChange(): void
    {
        $anyWindowOpen = $this->checkAnyWindowOpen();
        $wasWindowOpen = $this->isWindowOpen();

        // Update the WindowOpen indicator variable
        SetValue($this->GetIDForIdent('WindowOpen'), $anyWindowOpen);

        if ($anyWindowOpen && !$wasWindowOpen) {
            // Window just opened - save current target temperature and apply window open temperature
            $currentTarget = @GetValue($this->GetIDForIdent('TargetTemperature'));
            $this->WriteAttributeFloat('TempBeforeWindowOpen', $currentTarget);

            $windowOpenTemp = @GetValue($this->GetIDForIdent('WindowOpenTemp'));
            SetValue($this->GetIDForIdent('TargetTemperature'), $windowOpenTemp);
            $this->ApplyTemperature();
        } elseif (!$anyWindowOpen && $wasWindowOpen) {
            // Window just closed - apply current schedule mode (or night setback if active)
            if ($this->isNightSetbackActive()) {
                $newTemp = @GetValue($this->GetIDForIdent('NightSetbackTemp'));
                SetValue($this->GetIDForIdent('TargetTemperature'), $newTemp);
                $this->ApplyTemperature();
            } else {
                $this->applyHeatingMode($this->getCurrentMode());
            }
        }
    }

    private function maintainScheduleEvent(): void
    {
        $scheduleID = $this->ReadAttributeInteger('ScheduleEventID');

        // Check if existing schedule event still exists
        if ($scheduleID > 0 && !@IPS_EventExists($scheduleID)) {
            $scheduleID = 0;
        }

        // Check if existing schedule has correct number of groups (7) and actions (5)
        if ($scheduleID > 0) {
            $event = @IPS_GetEvent($scheduleID);
            if ($event && (count($event['ScheduleGroups']) !== 7 || count($event['ScheduleActions']) !== 5)) {
                // Old schedule format, delete and recreate
                IPS_DeleteEvent($scheduleID);
                $scheduleID = 0;
            }
        }

        // Create new schedule event if needed
        if ($scheduleID === 0) {
            $scheduleID = IPS_CreateEvent(2); // 2 = Schedule event
            IPS_SetParent($scheduleID, $this->InstanceID);
            IPS_SetName($scheduleID, $this->Translate('Weekly Schedule'));
            IPS_SetEventActive($scheduleID, true);

            // Set up schedule groups for each day individually (7 groups)
            // Day bits: 1=Monday, 2=Tuesday, 4=Wednesday, 8=Thursday, 16=Friday, 32=Saturday, 64=Sunday
            IPS_SetEventScheduleGroup($scheduleID, 0, 1);   // Monday
            IPS_SetEventScheduleGroup($scheduleID, 1, 2);   // Tuesday
            IPS_SetEventScheduleGroup($scheduleID, 2, 4);   // Wednesday
            IPS_SetEventScheduleGroup($scheduleID, 3, 8);   // Thursday
            IPS_SetEventScheduleGroup($scheduleID, 4, 16);  // Friday
            IPS_SetEventScheduleGroup($scheduleID, 5, 32);  // Saturday
            IPS_SetEventScheduleGroup($scheduleID, 6, 64);  // Sunday

            // Set up schedule actions in order: Off, Comfort, Eco, Away, Boost
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_OFF, $this->Translate('Off'), 0x9E9E9E, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_OFF . ');');
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_COMFORT, $this->Translate('Comfort'), 0xFF6B35, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_COMFORT . ');');
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_ECO, $this->Translate('Eco'), 0x4CAF50, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_ECO . ');');
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_AWAY, $this->Translate('Away'), 0x03A9F4, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_AWAY . ');');
            IPS_SetEventScheduleAction($scheduleID, self::ACTION_BOOST, $this->Translate('Boost'), 0xF44336, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_BOOST . ');');

            // Set default schedule points for all days - entirely Off
            for ($group = 0; $group <= 6; $group++) {
                IPS_SetEventScheduleGroupPoint($scheduleID, $group, 0, 0, 0, 0, self::ACTION_OFF);
            }

            $this->WriteAttributeInteger('ScheduleEventID', $scheduleID);
        }
    }

    private function initializeVariableDefault(string $ident, $defaultValue): void
    {
        $varID = @$this->GetIDForIdent($ident);
        if (!$varID || !@IPS_VariableExists($varID)) {
            return;
        }

        $variable = IPS_GetVariable($varID);
        
        $lastChange = $variable['VariableChanged'];
        $created = $variable['VariableUpdated'];
        
        if ($lastChange == 0 || abs($lastChange - $created) < 2) {
            SetValue($varID, $defaultValue);
        }
    }

    private function getPresentationIDByCaption(string $caption): string
    {
        $presentationsData = IPS_GetPresentations();
        $presentations = is_string($presentationsData) ? json_decode($presentationsData, true) : $presentationsData;
        if (!is_array($presentations)) {
            return '';
        }
        foreach ($presentations as $presentation) {
            if (isset($presentation['caption']) && $presentation['caption'] === $caption) {
                return $presentation['id'];
            }
        }
        return '';
    }
}
