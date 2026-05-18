<?php

/*
 * AdvancedHeatingControl for IP-Symcon
 *
 * SPDX-License-Identifier: EUPL-1.2
 * Copyright (c) 2026 mwlf01
 *
 * Licensed under the EUPL, Version 1.2. See the LICENSE file for the full text.
 */

declare(strict_types=1);

class AdvancedHeatingControl extends IPSModule
{
    private const ACTION_OFF = 1;
    private const ACTION_COMFORT = 2;
    private const ACTION_ECO = 3;
    private const ACTION_AWAY = 4;
    private const ACTION_BOOST = 5;

    // Module status codes
    private const STATUS_ACTIVE = 102;
    private const STATUS_NO_THERMOSTATS = 104;
    private const STATUS_INVALID_RANGE = 201;

    // Mode constants (variable values, not schedule action IDs)
    private const MODE_OFF = 0;
    private const MODE_COMFORT = 1;
    private const MODE_ECO = 2;
    private const MODE_AWAY = 3;
    private const MODE_BOOST = 4;

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

        // ---- Properties: Schedule Plans (list of weekly schedules) ----
        $this->RegisterPropertyString('SchedulePlans', '[]');

        // ---- Properties: Temperature Range ----
        $this->RegisterPropertyFloat('MinTemperature', 5.0);
        $this->RegisterPropertyFloat('MaxTemperature', 30.0);
        $this->RegisterPropertyFloat('TemperatureStep', 0.5);

        // ---- Attributes ----
        // ScheduleEventID is kept for backward-compatible migration from versions <= 1.2.1
        $this->RegisterAttributeInteger('ScheduleEventID', 0);
        // Tracks event IDs the module currently manages, for cleanup on removal
        $this->RegisterAttributeString('ManagedScheduleEventIDs', '[]');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Track which variables already exist before MaintainVariable calls
        $existingVars = [];
        foreach (['TargetTemperature', 'CurrentTemperature', 'HeatingMode', 'ComfortTemp', 'EcoTemp', 'AwayTemp', 'BoostTemp', 'NightSetbackActive', 'NightSetbackTemp', 'ManualOperationBlocked', 'WindowOpen', 'WindowOpenTemp', 'AwayModeActive'] as $ident) {
            $id = @$this->GetIDForIdent($ident);
            if ($id && @IPS_VariableExists($id)) {
                $existingVars[$ident] = true;
            }
        }

        $minTemp = $this->ReadPropertyFloat('MinTemperature');
        $maxTemp = $this->ReadPropertyFloat('MaxTemperature');
        $stepTemp = $this->ReadPropertyFloat('TemperatureStep');

        // Reusable temperature slider presentation
        $tempPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
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
        $this->initializeVariableDefault('TargetTemperature', 21.0, $existingVars);

        // Current Room Temperature variable (position 2) - Value Presentation (no action)
        $this->MaintainVariable('CurrentTemperature', $this->Translate('Current Temperature'), VARIABLETYPE_FLOAT, [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => ' °C',
            'DIGITS' => 1,
            'USAGE_TYPE' => 1
        ], 2, true);

        // Heating Mode variable (position 3) - Enumeration presentation
        $heatingModeOptions = json_encode([
            ['Value' => self::MODE_OFF, 'Caption' => $this->Translate('Off'), 'IconActive' => true, 'IconValue' => 'Power', 'Color' => 0x9E9E9E],
            ['Value' => self::MODE_COMFORT, 'Caption' => $this->Translate('Comfort'), 'IconActive' => true, 'IconValue' => 'Temperature', 'Color' => 0xFF6B35],
            ['Value' => self::MODE_ECO, 'Caption' => $this->Translate('Eco'), 'IconActive' => true, 'IconValue' => 'Leaf', 'Color' => 0x4CAF50],
            ['Value' => self::MODE_AWAY, 'Caption' => $this->Translate('Away'), 'IconActive' => true, 'IconValue' => 'Motion', 'Color' => 0x03A9F4],
            ['Value' => self::MODE_BOOST, 'Caption' => $this->Translate('Boost'), 'IconActive' => true, 'IconValue' => 'Flame', 'Color' => 0xF44336]
        ]);
        $this->MaintainVariable('HeatingMode', $this->Translate('Heating Mode'), VARIABLETYPE_INTEGER, [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'ICON' => 'Temperature',
            'OPTIONS' => $heatingModeOptions
        ], 3, true);
        $this->EnableAction('HeatingMode');
        $this->initializeVariableDefault('HeatingMode', self::MODE_OFF, $existingVars);

        // Comfort Temperature variable (position 4)
        $this->MaintainVariable('ComfortTemp', $this->Translate('Comfort Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 4, true);
        $this->EnableAction('ComfortTemp');
        $this->initializeVariableDefault('ComfortTemp', 21.0, $existingVars);

        // Eco Temperature variable (position 5)
        $this->MaintainVariable('EcoTemp', $this->Translate('Eco Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 5, true);
        $this->EnableAction('EcoTemp');
        $this->initializeVariableDefault('EcoTemp', 18.0, $existingVars);

        // Away Temperature variable (position 6)
        $this->MaintainVariable('AwayTemp', $this->Translate('Away Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 6, true);
        $this->EnableAction('AwayTemp');
        $this->initializeVariableDefault('AwayTemp', 15.0, $existingVars);

        // Boost Temperature variable (position 7)
        $this->MaintainVariable('BoostTemp', $this->Translate('Boost Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 7, true);
        $this->EnableAction('BoostTemp');
        $this->initializeVariableDefault('BoostTemp', 24.0, $existingVars);

        // Reusable switch presentation
        $switchPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON_ON' => 'Power',
            'ICON_OFF' => 'Power',
            'COLOR_ON' => 0x4CAF50,
            'COLOR_OFF' => 0x9E9E9E
        ];

        // Night Setback Active variable (position 8)
        $this->MaintainVariable('NightSetbackActive', $this->Translate('Night Setback Active'), VARIABLETYPE_BOOLEAN, $switchPresentation, 8, true);
        $this->EnableAction('NightSetbackActive');
        $this->initializeVariableDefault('NightSetbackActive', false, $existingVars);

        // Night Setback Temperature variable (position 9)
        $this->MaintainVariable('NightSetbackTemp', $this->Translate('Night Setback Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 9, true);
        $this->EnableAction('NightSetbackTemp');
        $this->initializeVariableDefault('NightSetbackTemp', 16.0, $existingVars);

        // Manual Operation Blocked variable (position 10) - Switch with lock icon
        $lockSwitchPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'ICON_ON' => 'Lock',
            'ICON_OFF' => 'LockOpen',
            'COLOR_ON' => 0xF44336,
            'COLOR_OFF' => 0x9E9E9E
        ];
        $this->MaintainVariable('ManualOperationBlocked', $this->Translate('Manual Operation Blocked'), VARIABLETYPE_BOOLEAN, $lockSwitchPresentation, 10, true);
        $this->EnableAction('ManualOperationBlocked');
        $this->initializeVariableDefault('ManualOperationBlocked', false, $existingVars);

        // Window Open indicator variable (position 11) - read-only value presentation
        $windowOpenOptions = json_encode([
            ['Value' => false, 'Caption' => $this->Translate('Closed'), 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 0x4CAF50],
            ['Value' => true, 'Caption' => $this->Translate('Open'), 'IconActive' => false, 'IconValue' => '', 'ColorActive' => true, 'ColorValue' => 0x03A9F4]
        ]);
        $this->MaintainVariable('WindowOpen', $this->Translate('Window Open'), VARIABLETYPE_BOOLEAN, [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON' => 'Window',
            'OPTIONS' => $windowOpenOptions
        ], 11, true);
        $this->initializeVariableDefault('WindowOpen', false, $existingVars);

        // Window Open Temperature variable (position 12)
        $this->MaintainVariable('WindowOpenTemp', $this->Translate('Window Open Temperature'), VARIABLETYPE_FLOAT, $tempPresentation, 12, true);
        $this->EnableAction('WindowOpenTemp');
        $this->initializeVariableDefault('WindowOpenTemp', 12.0, $existingVars);

        // Away Mode Active variable (position 13) - permanent away temperature override
        $this->MaintainVariable('AwayModeActive', $this->Translate('Away Mode Active'), VARIABLETYPE_BOOLEAN, $switchPresentation, 13, true);
        $this->EnableAction('AwayModeActive');
        $this->initializeVariableDefault('AwayModeActive', false, $existingVars);

        // Remove obsolete variables from earlier versions
        $this->MaintainVariable('FrostTemp', '', VARIABLETYPE_FLOAT, '', 0, false);
        $this->MaintainVariable('HeatingActive', '', VARIABLETYPE_BOOLEAN, '', 0, false);
        $this->MaintainVariable('NightTemp', '', VARIABLETYPE_FLOAT, '', 0, false);

        // Validate temperature range — abort initialization if invalid
        if ($minTemp >= $maxTemp) {
            $this->SetStatus(self::STATUS_INVALID_RANGE);
            return;
        }

        // Clamp existing setpoints into the (possibly new) range
        $this->clampSetpointsToRange($minTemp, $maxTemp);

        // One-time migration from old single-schedule layout (<= 1.2.1) to schedule-plan list
        if ($this->migrateLegacyScheduleIfNeeded()) {
            // Migration triggered a recursive ApplyChanges via IPS_ApplyChanges - exit early
            return;
        }

        // Create or update all configured weekly schedule events.
        // If new event IDs were written back into the property, this triggers a recursive
        // ApplyChanges so the configuration form picks up the new IDs.
        if ($this->maintainSchedulePlans()) {
            return;
        }

        // Register message subscriptions
        $this->registerMessages();

        // Update status based on configuration
        $thermostats = $this->getThermostats();
        if (empty($thermostats)) {
            $this->SetStatus(self::STATUS_NO_THERMOSTATS);
        } else {
            $this->SetStatus(self::STATUS_ACTIVE);
        }

        // Initial temperature update
        $this->updateCurrentTemperature();

        // Synchronize window-open indicator with real contact state
        $this->handleWindowContactChange();
    }

    public function Destroy()
    {
        // Clean up all managed schedule events when this instance is deleted
        $managed = $this->getManagedEventIDs();
        foreach ($managed as $eventID) {
            if ($eventID > 0 && @IPS_EventExists($eventID)) {
                @IPS_DeleteEvent($eventID);
            }
        }

        // Also clean up legacy attribute if still present
        $legacyID = $this->ReadAttributeInteger('ScheduleEventID');
        if ($legacyID > 0 && @IPS_EventExists($legacyID) && !in_array($legacyID, $managed, true)) {
            @IPS_DeleteEvent($legacyID);
        }

        parent::Destroy();
    }

    /* ================= Configuration Form ================= */
    public function GetConfigurationForm(): string
    {
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
                    'caption' => 'Weekly Schedules',
                    'items' => [
                        [
                            'type' => 'Label',
                            'caption' => 'Each entry creates a weekly schedule event below this instance with the actions Off, Comfort, Eco, Away and Boost.'
                        ],
                        [
                            'type' => 'Label',
                            'caption' => 'Plans run in parallel. Activate or deactivate individual plans via the schedule event\'s built-in switch. If multiple plans fire at the same time, the last action wins.'
                        ],
                        [
                            'type' => 'List',
                            'name' => 'SchedulePlans',
                            'caption' => 'Schedule Plans',
                            'rowCount' => 5,
                            'add' => true,
                            'delete' => true,
                            'columns' => [
                                [
                                    'caption' => 'Name',
                                    'name' => 'Name',
                                    'width' => 'auto',
                                    'add' => '',
                                    'edit' => [
                                        'type' => 'ValidationTextBox'
                                    ]
                                ],
                                [
                                    'caption' => 'Event ID',
                                    'name' => 'EventID',
                                    'width' => '120px',
                                    'add' => 0,
                                    'edit' => [
                                        'type' => 'NumberSpinner',
                                        'enabled' => false
                                    ]
                                ]
                            ]
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
                ],
                [
                    'code' => 201,
                    'icon' => 'error',
                    'caption' => 'Invalid temperature range (minimum must be below maximum)'
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
                // Update target if away mode active OR currently in away schedule mode, and neither night setback nor window active
                if (($this->isAwayModeActive() || $this->getCurrentMode() === 3) && !$this->isNightSetbackActive() && !$this->isWindowOpen()) {
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
                        // Night setback deactivated - check away mode, then fall back to schedule
                        if ($this->isAwayModeActive()) {
                            $awayTemp = @GetValue($this->GetIDForIdent('AwayTemp'));
                            SetValue($this->GetIDForIdent('TargetTemperature'), $awayTemp);
                            $this->ApplyTemperature();
                        } else {
                            $this->applyHeatingMode($this->getCurrentMode());
                        }
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

            case 'AwayModeActive':
                $active = (bool)$Value;
                SetValue($this->GetIDForIdent('AwayModeActive'), $active);
                // Only apply if window and night setback are not active
                if (!$this->isWindowOpen() && !$this->isNightSetbackActive()) {
                    if ($active) {
                        // Away mode activated - apply away temperature
                        $awayTemp = @GetValue($this->GetIDForIdent('AwayTemp'));
                        SetValue($this->GetIDForIdent('TargetTemperature'), $awayTemp);
                        $this->ApplyTemperature();
                    } else {
                        // Away mode deactivated - reapply current schedule mode
                        $this->applyHeatingMode($this->getCurrentMode());
                    }
                }
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
        $modeMap = [
            self::ACTION_OFF     => self::MODE_OFF,
            self::ACTION_COMFORT => self::MODE_COMFORT,
            self::ACTION_ECO     => self::MODE_ECO,
            self::ACTION_AWAY    => self::MODE_AWAY,
            self::ACTION_BOOST   => self::MODE_BOOST,
        ];

        if (!array_key_exists($ActionID, $modeMap)) {
            $this->LogMessage('Unknown schedule action ID: ' . $ActionID, KL_WARNING);
            return;
        }

        $mode = $modeMap[$ActionID];

        // Always update the heating mode variable (so we know what schedule wants)
        SetValue($this->GetIDForIdent('HeatingMode'), $mode);

        // Only apply temperature to thermostats if night setback, away mode, and window are NOT active
        if (!$this->isNightSetbackActive() && !$this->isAwayModeActive() && !$this->isWindowOpen()) {
            $this->applyHeatingMode($mode);
        }
    }

    /* ================= Message Sink ================= */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message !== VM_UPDATE) {
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
        $targetTemp = (float)@GetValue($this->GetIDForIdent('TargetTemperature'));
        $tolerance = $this->getChangeTolerance();
        $thermostats = $this->getThermostats();

        foreach ($thermostats as $thermostat) {
            $thermostatID = (int)$thermostat['ThermostatID'];
            if ($thermostatID > 0 && @IPS_VariableExists($thermostatID)) {
                $current = (float)@GetValue($thermostatID);
                if (abs($current - $targetTemp) > $tolerance) {
                    $this->safeRequestAction($thermostatID, $targetTemp, 'ApplyTemperature');
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
        // Only apply if night setback and away mode are not active
        if (!$this->isNightSetbackActive() && !$this->isAwayModeActive()) {
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
            // Night setback deactivated - check away mode, then fall back to schedule
            if ($this->isAwayModeActive()) {
                $awayTemp = @GetValue($this->GetIDForIdent('AwayTemp'));
                SetValue($this->GetIDForIdent('TargetTemperature'), $awayTemp);
                $this->ApplyTemperature();
            } else {
                $this->applyHeatingMode($this->getCurrentMode());
            }
        }
    }

    /**
     * Enable or disable permanent away mode
     * @param bool $Active True to enable, false to disable
     */
    public function SetPermanentAwayMode(bool $Active): void
    {
        SetValue($this->GetIDForIdent('AwayModeActive'), $Active);
        // Only apply if window and night setback are not active
        if (!$this->isWindowOpen() && !$this->isNightSetbackActive()) {
            if ($Active) {
                $awayTemp = @GetValue($this->GetIDForIdent('AwayTemp'));
                SetValue($this->GetIDForIdent('TargetTemperature'), $awayTemp);
                $this->ApplyTemperature();
            } else {
                $this->applyHeatingMode($this->getCurrentMode());
            }
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
     * Get the event ID of the first configured schedule plan (backward compatibility).
     * Returns 0 if no plans are configured.
     * @return int Event ID of the first plan or 0
     */
    public function GetScheduleEventID(): int
    {
        $managed = $this->getManagedEventIDs();
        return $managed[0] ?? 0;
    }

    /**
     * Get the number of configured schedule plans.
     * @return int Number of plans
     */
    public function GetSchedulePlanCount(): int
    {
        return count($this->getManagedEventIDs());
    }

    /**
     * Get the event ID of a specific schedule plan by index (0-based).
     * @param int $Index Zero-based index of the plan
     * @return int Event ID, or 0 if the index is out of range
     */
    public function GetSchedulePlanEventID(int $Index): int
    {
        $managed = $this->getManagedEventIDs();
        return $managed[$Index] ?? 0;
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
                $this->RegisterMessage($thermostatID, VM_UPDATE);
            }
        }

        // Register temperature sensor variables
        $sensors = $this->getTemperatureSensors();
        foreach ($sensors as $sensor) {
            $sensorID = (int)$sensor['SensorID'];
            if ($sensorID > 0) {
                $this->RegisterMessage($sensorID, VM_UPDATE);
            }
        }

        // Register window contact variables
        $contacts = $this->getWindowContacts();
        foreach ($contacts as $contact) {
            $contactID = (int)$contact['ContactID'];
            if ($contactID > 0) {
                $this->RegisterMessage($contactID, VM_UPDATE);
            }
        }
    }

    private function updateCurrentTemperature(): void
    {
        $sensors = $this->getTemperatureSensors();
        if (empty($sensors)) {
            // Distinguish "nothing configured" from "all configured sensors invalid"
            $raw = @json_decode($this->ReadPropertyString('TemperatureSensors'), true);
            if (is_array($raw) && count($raw) > 0) {
                $this->LogMessage('All configured temperature sensors are invalid — CurrentTemperature not updated', KL_WARNING);
            }
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

    private function isAwayModeActive(): bool
    {
        $varID = @$this->GetIDForIdent('AwayModeActive');
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
        $currentTarget = (float)@GetValue($this->GetIDForIdent('TargetTemperature'));
        $tolerance = $this->getChangeTolerance();

        // Check if the change was significant (not just our own update)
        if (abs($newTemp - $currentTarget) < $tolerance) {
            return;
        }

        // Check if window is open - block all manual changes
        if ($this->isWindowOpen()) {
            // Immediately revert the thermostat to our target temperature
            $this->safeRequestAction($thermostatID, $currentTarget, 'revert window-open');
            return;
        }

        // Check if manual operation is blocked
        if ($this->isManualOperationBlocked()) {
            // Immediately revert the thermostat to our target temperature
            $this->safeRequestAction($thermostatID, $currentTarget, 'revert manual-blocked');
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
                $current = (float)@GetValue($id);
                if (abs($current - $newTemp) > $tolerance) {
                    $this->safeRequestAction($id, $newTemp, 'sync thermostats');
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
            // Window just opened - apply window open temperature
            $windowOpenTemp = @GetValue($this->GetIDForIdent('WindowOpenTemp'));
            SetValue($this->GetIDForIdent('TargetTemperature'), $windowOpenTemp);
            $this->ApplyTemperature();
        } elseif (!$anyWindowOpen && $wasWindowOpen) {
            // Window just closed - apply by priority: night setback > away mode > schedule
            if ($this->isNightSetbackActive()) {
                $newTemp = @GetValue($this->GetIDForIdent('NightSetbackTemp'));
                SetValue($this->GetIDForIdent('TargetTemperature'), $newTemp);
                $this->ApplyTemperature();
            } elseif ($this->isAwayModeActive()) {
                $awayTemp = @GetValue($this->GetIDForIdent('AwayTemp'));
                SetValue($this->GetIDForIdent('TargetTemperature'), $awayTemp);
                $this->ApplyTemperature();
            } else {
                $this->applyHeatingMode($this->getCurrentMode());
            }
        }
    }

    /**
     * One-time migration from versions <= 1.2.1 that stored a single event ID in an attribute.
     * Moves the legacy event into the new SchedulePlans list as "Standard".
     * @return bool True if a recursive ApplyChanges was triggered and the current run should abort.
     */
    private function migrateLegacyScheduleIfNeeded(): bool
    {
        $legacyID = $this->ReadAttributeInteger('ScheduleEventID');
        if ($legacyID <= 0 || !@IPS_EventExists($legacyID)) {
            // Nothing to migrate or legacy event already gone
            if ($legacyID > 0) {
                $this->WriteAttributeInteger('ScheduleEventID', 0);
            }
            return false;
        }

        $plansRaw = $this->ReadPropertyString('SchedulePlans');
        $plans = json_decode($plansRaw, true);
        if (!is_array($plans)) {
            $plans = [];
        }

        // Skip migration if the new list already references the legacy event or is non-empty
        foreach ($plans as $plan) {
            if ((int)($plan['EventID'] ?? 0) === $legacyID) {
                $this->WriteAttributeInteger('ScheduleEventID', 0);
                return false;
            }
        }

        if (count($plans) > 0) {
            // User already set up plans manually - keep legacy event but unlink it from the module
            $this->WriteAttributeInteger('ScheduleEventID', 0);
            return false;
        }

        // Migrate: prepend legacy event as "Standard" plan
        $plans[] = ['Name' => $this->Translate('Standard'), 'EventID' => $legacyID];
        IPS_SetProperty($this->InstanceID, 'SchedulePlans', json_encode($plans));
        $this->WriteAttributeInteger('ScheduleEventID', 0);
        IPS_ApplyChanges($this->InstanceID);
        return true;
    }

    /**
     * Reconcile the configured SchedulePlans list with actual IPS schedule events:
     *  - create events for new plan entries
     *  - reuse and rename existing events
     *  - delete events that were managed but are no longer referenced
     *  - write back updated EventIDs into the property so the UI shows them
     *
     * @return bool True if event IDs were written back and a recursive ApplyChanges was triggered.
     *              The caller must abort the current run in that case.
     */
    private function maintainSchedulePlans(): bool
    {
        $plansRaw = $this->ReadPropertyString('SchedulePlans');
        $plans = json_decode($plansRaw, true);
        if (!is_array($plans)) {
            $plans = [];
        }

        $previouslyManaged = $this->getManagedEventIDs();
        $stillManaged = [];
        $propertyChanged = false;

        foreach ($plans as $index => $plan) {
            $name = trim((string)($plan['Name'] ?? ''));
            if ($name === '') {
                $name = $this->Translate('Weekly Schedule') . ' ' . ($index + 1);
            }
            $eventID = (int)($plan['EventID'] ?? 0);

            if ($eventID > 0 && !@IPS_EventExists($eventID)) {
                // Stored ID points to a deleted event - create a fresh one
                $eventID = 0;
            }

            $isNewEvent = ($eventID === 0);
            if ($isNewEvent) {
                $eventID = IPS_CreateEvent(2); // 2 = Schedule event
                IPS_SetParent($eventID, $this->InstanceID);
                IPS_SetEventActive($eventID, true);
                $plans[$index]['EventID'] = $eventID;
                $propertyChanged = true;
            }

            // Keep event name in sync with the configured plan name
            $existingName = @IPS_GetName($eventID);
            if ($existingName !== $name) {
                IPS_SetName($eventID, $name);
            }

            $this->ensureScheduleStructure($eventID, $isNewEvent);

            $stillManaged[] = $eventID;
        }

        // Delete events that were previously managed but are no longer in the plan list
        $toDelete = array_diff($previouslyManaged, $stillManaged);
        foreach ($toDelete as $eventID) {
            if ($eventID > 0 && @IPS_EventExists($eventID)) {
                @IPS_DeleteEvent($eventID);
            }
        }

        $this->WriteAttributeString('ManagedScheduleEventIDs', json_encode($stillManaged));

        if ($propertyChanged) {
            // Persist the newly assigned event IDs so the configuration form shows them.
            // Trigger a recursive ApplyChanges so the property is committed; on the recursive
            // run no new IDs are assigned, so we won't loop.
            IPS_SetProperty($this->InstanceID, 'SchedulePlans', json_encode($plans));
            IPS_ApplyChanges($this->InstanceID);
            return true;
        }

        return false;
    }

    /**
     * Ensure the given schedule event has our 5 actions and 7 day-groups.
     * Existing user-added groups/actions and switch points are left untouched.
     */
    private function ensureScheduleStructure(int $scheduleID, bool $isNewEvent): void
    {
        $dayBits = [1, 2, 4, 8, 16, 32, 64]; // Mon..Sun

        if ($isNewEvent) {
            foreach ($dayBits as $groupID => $bits) {
                IPS_SetEventScheduleGroup($scheduleID, $groupID, $bits);
            }
        } else {
            $existingGroups = $this->getExistingScheduleGroupIDs($scheduleID);
            foreach ($dayBits as $groupID => $bits) {
                if (!in_array($groupID, $existingGroups, true)) {
                    IPS_SetEventScheduleGroup($scheduleID, $groupID, $bits);
                }
            }
        }

        // Idempotent action setup - safe to call on existing events without affecting switch points
        IPS_SetEventScheduleAction($scheduleID, self::ACTION_OFF, $this->Translate('Off'), 0x9E9E9E, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_OFF . ');');
        IPS_SetEventScheduleAction($scheduleID, self::ACTION_COMFORT, $this->Translate('Comfort'), 0xFF6B35, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_COMFORT . ');');
        IPS_SetEventScheduleAction($scheduleID, self::ACTION_ECO, $this->Translate('Eco'), 0x4CAF50, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_ECO . ');');
        IPS_SetEventScheduleAction($scheduleID, self::ACTION_AWAY, $this->Translate('Away'), 0x03A9F4, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_AWAY . ');');
        IPS_SetEventScheduleAction($scheduleID, self::ACTION_BOOST, $this->Translate('Boost'), 0xF44336, 'AHC_ScheduleAction($_IPS[\'TARGET\'], ' . self::ACTION_BOOST . ');');

        if ($isNewEvent) {
            // Default schedule points for all days - entirely Off
            for ($group = 0; $group <= 6; $group++) {
                IPS_SetEventScheduleGroupPoint($scheduleID, $group, 0, 0, 0, 0, self::ACTION_OFF);
            }
        }
    }

    private function getManagedEventIDs(): array
    {
        $raw = $this->ReadAttributeString('ManagedScheduleEventIDs');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_map('intval', $decoded);
    }

    private function getExistingScheduleGroupIDs(int $scheduleID): array
    {
        $event = @IPS_GetEvent($scheduleID);
        if (!is_array($event) || !isset($event['ScheduleGroups']) || !is_array($event['ScheduleGroups'])) {
            return [];
        }
        $groupIDs = [];
        foreach ($event['ScheduleGroups'] as $group) {
            if (isset($group['ID'])) {
                $groupIDs[] = (int)$group['ID'];
            }
        }
        return $groupIDs;
    }

    private function initializeVariableDefault(string $ident, $defaultValue, array $existingVars = []): void
    {
        // Only set defaults for newly created variables
        if (isset($existingVars[$ident])) {
            return;
        }

        $varID = @$this->GetIDForIdent($ident);
        if (!$varID || !@IPS_VariableExists($varID)) {
            return;
        }

        SetValue($varID, $defaultValue);
    }

    private function clampSetpointsToRange(float $minTemp, float $maxTemp): void
    {
        $idents = ['TargetTemperature', 'ComfortTemp', 'EcoTemp', 'AwayTemp', 'BoostTemp', 'NightSetbackTemp', 'WindowOpenTemp'];
        foreach ($idents as $ident) {
            $varID = @$this->GetIDForIdent($ident);
            if (!$varID || !@IPS_VariableExists($varID)) {
                continue;
            }
            $val = (float)@GetValue($varID);
            $clamped = max($minTemp, min($maxTemp, $val));
            if (abs($val - $clamped) > 0.0001) {
                SetValue($varID, $clamped);
            }
        }
    }

    private function getChangeTolerance(): float
    {
        // Half the step size, with a floor for safe float comparison
        $step = $this->ReadPropertyFloat('TemperatureStep');
        return max($step / 2.0, 0.05);
    }

    private function safeRequestAction(int $variableID, $value, string $context = ''): bool
    {
        $result = @RequestAction($variableID, $value);
        if ($result === false) {
            $name = @IPS_GetName($variableID);
            $valueStr = is_scalar($value) ? (string)$value : json_encode($value);
            $this->LogMessage(
                sprintf(
                    'RequestAction failed for variable #%d (%s) with value %s%s',
                    $variableID,
                    $name !== false ? $name : '?',
                    $valueStr,
                    $context !== '' ? ' [' . $context . ']' : ''
                ),
                KL_WARNING
            );
            return false;
        }
        return true;
    }
}
