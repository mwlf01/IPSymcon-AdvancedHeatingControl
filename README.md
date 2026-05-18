# AdvancedHeatingControl for IP-Symcon

[![IP-Symcon Version](https://img.shields.io/badge/IP--Symcon-8.1+-blue.svg)](https://www.symcon.de)
[![License: EUPL-1.2](https://img.shields.io/badge/License-EUPL--1.2-blue.svg)](LICENSE)

A powerful IP-Symcon module for centralized heating control with multiple thermostats, room temperature sensors, and weekly scheduling.

**[Deutsche Version](README.de.md)**

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Thermostats](#thermostats)
  - [Temperature Sensors](#temperature-sensors)
  - [Window Contacts](#window-contacts)
  - [Weekly Schedules](#weekly-schedules)
- [Variables](#variables)
- [PHP Functions](#php-functions)
- [License](#license)

---

## Features

- **Multi-Thermostat Control**: Register any number of heating thermostats and control them with a single target temperature
- **Room Temperature Monitoring**: 
  - Multiple temperature sensors supported
  - Automatic averaging of sensor values
  - Real-time room temperature display
- **Heating Modes** (in order):
  - **Off**: Heating disabled (minimum temperature)
  - **Comfort**: Normal comfortable temperature (~21°C)
  - **Eco**: Energy-saving reduced temperature (~18°C)
  - **Away**: Reduced temperature when nobody is home (~15°C)
  - **Boost**: Higher temperature for quick warm-up (~24°C)
- **Weekly Schedules**:
  - Configure any number of parallel weekly schedule plans via the instance configuration
  - Each plan is its own IP-Symcon schedule event with a visual editor in the console
  - Five schedule actions per plan: Comfort, Eco, Away, Boost, Off
  - Individual configuration for each day of the week
  - Activate or deactivate individual plans via the schedule event's built-in switch
- **Night Setback**:
  - Override schedule with a fixed setback temperature
  - Enable/disable via boolean variable
  - When disabled, current schedule mode is automatically reapplied
- **Permanent Away Mode**:
  - Override schedule with the configured away temperature
  - Enable/disable via boolean variable
  - Night setback has higher priority than away mode
  - When disabled, current schedule mode is automatically reapplied
- **Window Contacts**:
  - Configure multiple window contact sensors
  - Automatic temperature reduction when windows are open
  - Temperature is restored when all windows are closed
  - Manual thermostat changes blocked while windows are open
- **Bidirectional Thermostat Sync**:
  - Changes made directly on thermostats are synchronized back to the module
  - All other thermostats are updated automatically
  - Optional blocking of manual thermostat operation
- **User-Controlled Settings**:
  - All temperature presets adjustable via variables in visualization
  - Heating mode can be changed manually
- **Thermostat Visualization**:
  - Compatible with IP-Symcon's built-in thermostat tile visualization
  - Target and current temperature displayed as thermostat widget

---

## Requirements

- IP-Symcon 8.1 or higher

---

## Installation

### Via Module Store (Recommended)

1. Open IP-Symcon Console
2. Navigate to **Modules** > **Module Store**
3. Search for "AdvancedHeatingControl"
4. Click **Install**

### Manual Installation via Git

1. Open IP-Symcon Console
2. Navigate to **Modules** > **Modules**
3. Click **Add** (Plus icon)
4. Select **Add Module from URL**
5. Enter: `https://github.com/mwlf01/IPSymcon-AdvancedHeatingControl.git`
6. Click **OK**

### Manual Installation (File Copy)

1. Clone or download this repository
2. Copy the folder to your IP-Symcon modules directory:
   - Windows: `C:\ProgramData\Symcon\modules\`
   - Linux: `/var/lib/symcon/modules/`
   - Docker: Check your volume mapping
3. Reload modules in IP-Symcon Console

---

## Configuration

After installation, create a new instance:

1. Navigate to **Objects** > **Add Object** > **Instance**
2. Search for "AdvancedHeatingControl" or "Advanced Heating Control"
3. Click **OK** to create the instance

### Thermostats

Register heating thermostat variables:

| Setting | Description |
|---------|-------------|
| **Thermostat Variable** | Select a float/integer variable that controls a thermostat's target temperature |
| **Name** | Optional friendly name for identification |

You can add as many thermostats as needed. All registered thermostats will receive the same target temperature.

### Temperature Sensors

Register room temperature sensor variables:

| Setting | Description |
|---------|-------------|
| **Sensor Variable** | Select a float/integer variable that provides room temperature |
| **Name** | Optional friendly name for identification |

Multiple sensors are averaged to calculate the displayed current room temperature.

### Window Contacts

Register window contact sensor variables:

| Setting | Description |
|---------|-------------|
| **Contact Variable** | Select a boolean variable that indicates window state (true = open) |
| **Name** | Optional friendly name for identification |

When any configured window is open:
- The window open temperature is applied immediately
- Manual thermostat changes are blocked
- When all windows are closed, the current schedule mode temperature is applied

### Temperature Settings

Configure the temperature range and step size:

| Setting | Description | Default |
|---------|-------------|---------|
| **Minimum Temperature** | Lowest allowed temperature (also used for Off mode) | 5.0°C |
| **Maximum Temperature** | Highest allowed temperature | 30.0°C |
| **Temperature Step** | Step size for temperature adjustments | 0.5°C |

### Weekly Schedules

The module supports any number of parallel weekly schedule events for time-based heating control:

- Add one or more entries to the **Schedule Plans** list in the instance configuration
- For each entry the module creates a schedule event below the instance with the actions **Off**, **Comfort**, **Eco**, **Away** and **Boost**
- The auto-generated event ID is written back into the list once the configuration is saved
- Configure switching times in the visual schedule editor (IP-Symcon Console or WebFront) of each event
- Activate or deactivate individual plans via the schedule event's built-in switch — no module configuration change needed
- Plans run in parallel; if multiple plans fire a switching action at the same time, the last action wins
- Removing a plan from the list deletes its schedule event on the next configuration save

Temperature presets (Comfort, Eco, Away, Boost) are configured via variables in the visualization, not in the instance configuration.

**Night Setback:** When enabled, the night setback temperature overrides any active schedule. Schedules continue to run in the background, but the setback temperature is applied to thermostats. When disabled, the current schedule mode is reapplied.

---

## Variables

The module creates the following variables:

| Variable | Type | Description |
|----------|------|-------------|
| **Target Temperature** | Float | Current target temperature for all thermostats |
| **Current Temperature** | Float | Average room temperature from all sensors |
| **Heating Mode** | Integer | Current mode (Off/Comfort/Eco/Away/Boost) |
| **Comfort Temperature** | Float | User-adjustable comfort temperature (default: 21°C) |
| **Eco Temperature** | Float | User-adjustable eco temperature (default: 18°C) |
| **Away Temperature** | Float | User-adjustable away temperature (default: 15°C) |
| **Boost Temperature** | Float | User-adjustable boost temperature (default: 24°C) |
| **Night Setback Active** | Boolean | Enable/disable night setback override |
| **Night Setback Temperature** | Float | Temperature used when night setback is active (default: 16°C) |
| **Manual Operation Blocked** | Boolean | When enabled, changes on thermostats are immediately reverted |
| **Window Open** | Boolean | Indicates if any configured window contact is open (read-only) |
| **Window Open Temperature** | Float | Temperature applied when a window is open (default: 12°C) |
| **Away Mode Active** | Boolean | Enable/disable permanent away mode (uses away temperature) |

---

## PHP Functions

The module provides the following public functions for use in scripts:

### SetTargetTemperature

Set the target temperature for all thermostats.

```php
AHC_SetTargetTemperature(int $InstanceID, float $Temperature);
```

**Parameters:**
- `$InstanceID` - ID of the AdvancedHeatingControl instance
- `$Temperature` - Target temperature in °C (within configured range)

**Example:**
```php
// Set target temperature to 22°C
AHC_SetTargetTemperature(12345, 22.0);
```

### ApplyTemperature

Apply the current target temperature to all thermostats.

```php
AHC_ApplyTemperature(int $InstanceID);
```

**Example:**
```php
AHC_ApplyTemperature(12345);
```

### SetHeatingMode

Set the heating mode.

```php
AHC_SetHeatingMode(int $InstanceID, int $Mode);
```

**Parameters:**
- `$InstanceID` - ID of the AdvancedHeatingControl instance
- `$Mode` - 0=Off, 1=Comfort, 2=Eco, 3=Away, 4=Boost

**Example:**
```php
// Set to Eco mode
AHC_SetHeatingMode(12345, 2);
```

### SetComfortMode / SetEcoMode / SetAwayMode / SetBoostMode / SetOff

Convenience functions to set specific modes.

```php
AHC_SetComfortMode(int $InstanceID);
AHC_SetEcoMode(int $InstanceID);
AHC_SetAwayMode(int $InstanceID);
AHC_SetBoostMode(int $InstanceID);
AHC_SetOff(int $InstanceID);
```

**Example:**
```php
// Switch to comfort mode
AHC_SetComfortMode(12345);

// Switch to boost mode for quick warm-up
AHC_SetBoostMode(12345);
```

### SetNightSetback

Enable or disable night setback override.

```php
AHC_SetNightSetback(int $InstanceID, bool $Active);
```

**Parameters:**
- `$InstanceID` - ID of the AdvancedHeatingControl instance
- `$Active` - true to enable, false to disable

**Example:**
```php
// Enable night setback
AHC_SetNightSetback(12345, true);

// Disable night setback (reapplies current schedule mode)
AHC_SetNightSetback(12345, false);
```

### SetPermanentAwayMode

Enable or disable permanent away mode.

```php
AHC_SetPermanentAwayMode(int $InstanceID, bool $Active);
```

**Parameters:**
- `$InstanceID` - ID of the AdvancedHeatingControl instance
- `$Active` - true to enable, false to disable

**Example:**
```php
// Enable permanent away mode
AHC_SetPermanentAwayMode(12345, true);

// Disable permanent away mode (reapplies current schedule mode)
AHC_SetPermanentAwayMode(12345, false);
```

### GetCurrentTemperature

Get the current room temperature.

```php
float AHC_GetCurrentTemperature(int $InstanceID);
```

**Returns:** Current temperature in °C

**Example:**
```php
$temp = AHC_GetCurrentTemperature(12345);
echo "Current room temperature: {$temp}°C";
```

### GetTargetTemperature

Get the current target temperature.

```php
float AHC_GetTargetTemperature(int $InstanceID);
```

**Returns:** Target temperature in °C

### GetScheduleEventID

Get the event ID of the first configured schedule plan (kept for backward compatibility).

```php
int AHC_GetScheduleEventID(int $InstanceID);
```

**Returns:** Event ID of the first schedule plan, or 0 if no plans are configured

### GetSchedulePlanCount

Get the number of configured schedule plans.

```php
int AHC_GetSchedulePlanCount(int $InstanceID);
```

**Returns:** Number of plans currently managed by the instance

### GetSchedulePlanEventID

Get the event ID of a specific schedule plan by its zero-based index.

```php
int AHC_GetSchedulePlanEventID(int $InstanceID, int $Index);
```

**Parameters:**
- `$InstanceID` - ID of the AdvancedHeatingControl instance
- `$Index` - Zero-based index of the plan (0 = first plan)

**Returns:** Event ID, or 0 if the index is out of range

**Example:**
```php
// Iterate over all schedule plans
$count = AHC_GetSchedulePlanCount(12345);
for ($i = 0; $i < $count; $i++) {
    $eventID = AHC_GetSchedulePlanEventID(12345, $i);
    echo "Plan $i: Event #$eventID" . PHP_EOL;
}
```

---

## Changelog

### Version 1.3.0
- Changed: Licence switched from MIT to EUPL-1.2 (see LICENSE for the full text). Releases up to 1.2.0 remain under MIT.
- Added: Multiple weekly schedule plans — configure any number of parallel schedules via the new **Schedule Plans** list in the instance configuration
- Added: `AHC_GetSchedulePlanCount` and `AHC_GetSchedulePlanEventID` for scripted access to the plan list
- Changed: Existing single schedule from earlier versions is automatically migrated into the new list as a plan named "Standard" on first configuration save
- Changed: `AHC_GetScheduleEventID` now returns the event ID of the first plan (backward compatible for installations with a single plan)
- Fixed: Incorrect example for `AHC_SetHeatingMode` in the documentation (mode `2` is Eco, not `1`)
- Fixed: Schedule events are no longer rebuilt from scratch when their action/group count differs — user-configured switch points are preserved
- Fixed: `Window Open` indicator now reflects the actual contact state on module startup
- Fixed: Failed `RequestAction` calls to thermostats are written to the module log instead of being silently dropped
- Fixed: Setpoint values are clamped to the current min/max range after configuration changes
- Improved: Variable presentations are identified via stable GUID constants instead of localized captions
- Improved: Unknown schedule action IDs trigger a log warning instead of silently switching to Off
- Improved: Echo-protection tolerance scales with the configured temperature step (correct behavior with integer thermostats)
- Improved: Module status reports an error when minimum temperature is not below maximum
- Removed: Dead `TempBeforeWindowOpen` attribute (was written but never read)

### Version 1.2.0
- Added: Permanent away mode (`Away Mode Active`) - permanently applies the away temperature, overriding the schedule
- Added: `AHC_SetPermanentAwayMode` PHP function for scripting
- Priority chain: Window Open > Night Setback > Away Mode > Schedule

### Version 1.1.0
- Fixed: Variable values (temperatures, modes, switches) are no longer reset to defaults when configuration changes are applied (e.g., adding a thermostat)

### Version 1.0.0
- Initial release
- Multi-thermostat control with unified target temperature
- Room temperature monitoring with sensor averaging
- Five heating modes (Off, Comfort, Eco, Away, Boost)
- Weekly schedule with individual day configuration using IP-Symcon's built-in schedule event
- Night setback override functionality
- Window contact support with automatic temperature reduction
- Manual operation blocking
- Configurable temperature range and step size
- Temperature presets adjustable via variables in visualization
- Full German localization

---

## Support

For issues, feature requests, or contributions, please visit:
- [GitHub Repository](https://github.com/mwlf01/IPSymcon-AdvancedHeatingControl)
- [GitHub Issues](https://github.com/mwlf01/IPSymcon-AdvancedHeatingControl/issues)
- [Symcon Community](https://community.symcon.de/) – User: **mwlf**

---

## License

This project is licensed under the **European Union Public Licence (EUPL) v. 1.2** — see the [LICENSE](LICENSE) file for the full text.

The EUPL is a copyleft licence: derivative works that are distributed must also be released under the EUPL or a compatible licence (e.g. GPL, AGPL, MPL, LGPL — see the appendix of the EUPL for the full compatibility list). Earlier releases up to version 1.2.0 remain available under the previous MIT licence.

The EUPL is published in 24 official language versions, all legally equivalent. Other language versions are available on the [official EU page](https://interoperable-europe.ec.europa.eu/collection/eupl/eupl-text-eupl-12).

---

## Author

**mwlf01**

- GitHub: [@mwlf01](https://github.com/mwlf01)
- Symcon Community: [mwlf](https://community.symcon.de/)
