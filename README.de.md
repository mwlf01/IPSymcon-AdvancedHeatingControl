# AdvancedHeatingControl für IP-Symcon

[![IP-Symcon Version](https://img.shields.io/badge/IP--Symcon-8.1+-blue.svg)](https://www.symcon.de)
[![Lizenz: EUPL-1.2](https://img.shields.io/badge/Lizenz-EUPL--1.2-blue.svg)](LICENSE)

Ein leistungsstarkes IP-Symcon-Modul zur zentralen Heizungssteuerung mit mehreren Thermostaten, Raumtemperatursensoren und Wochenplanung.

**[English Version](README.md)**

---

## Inhaltsverzeichnis

- [Funktionen](#funktionen)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Konfiguration](#konfiguration)
  - [Thermostate](#thermostate)
  - [Temperatursensoren](#temperatursensoren)
  - [Fensterkontakte](#fensterkontakte)
  - [Wochenpläne](#wochenpläne)
- [Variablen](#variablen)
- [PHP-Funktionen](#php-funktionen)
- [Lizenz](#lizenz)

---

## Funktionen

- **Multi-Thermostat-Steuerung**: Beliebig viele Heizungsthermostate registrieren und mit einer einzigen Solltemperatur steuern
- **Raumtemperaturüberwachung**: 
  - Mehrere Temperatursensoren unterstützt
  - Automatische Mittelwertbildung der Sensorwerte
  - Echtzeit-Anzeige der Raumtemperatur
- **Heizmodi** (in Reihenfolge):
  - **Aus**: Heizung deaktiviert (Mindesttemperatur)
  - **Komfort**: Normale Wohlfühltemperatur (~21°C)
  - **Eco**: Energiesparende reduzierte Temperatur (~18°C)
  - **Abwesend**: Reduzierte Temperatur bei Abwesenheit (~15°C)
  - **Boost**: Höhere Temperatur für schnelles Aufwärmen (~24°C)
- **Wochenpläne**:
  - Beliebig viele parallel laufende Wochenpläne über die Instanzkonfiguration
  - Jeder Plan ist ein eigenes IP-Symcon-Zeitplan-Ereignis mit visuellem Editor in der Konsole
  - Fünf Zeitplanaktionen pro Plan: Komfort, Eco, Abwesend, Boost, Aus
  - Individuelle Konfiguration für jeden Wochentag
  - Aktivieren oder Deaktivieren einzelner Pläne über den eingebauten Schalter am Wochenplan-Ereignis
- **Nachtabsenkung**:
  - Zeitplan mit fester Absenktemperatur überschreiben
  - Ein-/Ausschalten über Boolean-Variable
  - Bei Deaktivierung wird der aktuelle Zeitplanmodus automatisch wieder angewendet
- **Dauerhafter Abwesenheitsmodus**:
  - Zeitplan mit der konfigurierten Abwesend-Temperatur überschreiben
  - Ein-/Ausschalten über Boolean-Variable
  - Nachtabsenkung hat höhere Priorität als der Abwesenheitsmodus
  - Bei Deaktivierung wird der aktuelle Zeitplanmodus automatisch wieder angewendet
- **Fensterkontakte**:
  - Mehrere Fensterkontakt-Sensoren konfigurierbar
  - Automatische Temperaturabsenkung bei geöffneten Fenstern
  - Temperatur wird wiederhergestellt wenn alle Fenster geschlossen sind
  - Manuelle Thermostat-Änderungen werden bei offenen Fenstern blockiert
- **Bidirektionale Thermostat-Synchronisation**:
  - Änderungen direkt am Thermostat werden zurück zum Modul synchronisiert
  - Alle anderen Thermostate werden automatisch aktualisiert
  - Optionale Sperrung der manuellen Thermostat-Bedienung
- **Benutzergesteuerte Einstellungen**:
  - Alle Temperaturvoreinstellungen über Variablen in der Visualisierung anpassbar
  - Heizmodus kann manuell geändert werden
- **Thermostat-Visualisierung**:
  - Kompatibel mit der integrierten Thermostat-Kachel-Visualisierung von IP-Symcon
  - Soll- und Isttemperatur werden als Thermostat-Widget angezeigt

---

## Voraussetzungen

- IP-Symcon 8.1 oder höher

---

## Installation

### Über den Module Store (Empfohlen)

1. IP-Symcon-Konsole öffnen
2. Navigieren zu **Module** > **Module Store**
3. Nach "AdvancedHeatingControl" oder "Erweiterte Heizungssteuerung" suchen
4. Auf **Installieren** klicken

### Manuelle Installation über Git

1. IP-Symcon-Konsole öffnen
2. Navigieren zu **Module** > **Module**
3. Auf **Hinzufügen** (Plus-Symbol) klicken
4. **Modul von URL hinzufügen** auswählen
5. Eingeben: `https://github.com/mwlf01/IPSymcon-AdvancedHeatingControl.git`
6. Auf **OK** klicken

### Manuelle Installation (Dateikopie)

1. Dieses Repository klonen oder herunterladen
2. Den Ordner in das IP-Symcon-Modulverzeichnis kopieren:
   - Windows: `C:\ProgramData\Symcon\modules\`
   - Linux: `/var/lib/symcon/modules/`
   - Docker: Volume-Mapping prüfen
3. Module in der IP-Symcon-Konsole neu laden

---

## Konfiguration

Nach der Installation eine neue Instanz erstellen:

1. Navigieren zu **Objekte** > **Objekt hinzufügen** > **Instanz**
2. Nach "AdvancedHeatingControl" oder "Erweiterte Heizungssteuerung" suchen
3. Auf **OK** klicken um die Instanz zu erstellen

### Thermostate

Heizungsthermostat-Variablen registrieren:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Thermostat-Variable** | Float/Integer-Variable auswählen, die die Solltemperatur eines Thermostats steuert |
| **Name** | Optionaler Anzeigename zur Identifikation |

Sie können beliebig viele Thermostate hinzufügen. Alle registrierten Thermostate erhalten dieselbe Solltemperatur.

### Temperatursensoren

Raumtemperatursensor-Variablen registrieren:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Sensor-Variable** | Float/Integer-Variable auswählen, die die Raumtemperatur liefert |
| **Name** | Optionaler Anzeigename zur Identifikation |

Mehrere Sensoren werden gemittelt, um die angezeigte aktuelle Raumtemperatur zu berechnen.

### Fensterkontakte

Fensterkontakt-Sensor-Variablen registrieren:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Kontakt-Variable** | Boolean-Variable auswählen, die den Fensterstatus anzeigt (true = offen) |
| **Name** | Optionaler Anzeigename zur Identifikation |

Wenn ein konfiguriertes Fenster geöffnet ist:
- Die Fenster-offen-Temperatur wird sofort angewendet
- Manuelle Thermostat-Änderungen werden blockiert
- Wenn alle Fenster geschlossen sind, wird die aktuelle Zeitplanmodus-Temperatur angewendet

### Temperatureinstellungen

Konfigurieren Sie den Temperaturbereich und die Schrittgröße:

| Einstellung | Beschreibung | Standard |
|-------------|--------------|----------|
| **Mindesttemperatur** | Niedrigste erlaubte Temperatur (auch für Aus-Modus verwendet) | 5.0°C |
| **Maximaltemperatur** | Höchste erlaubte Temperatur | 30.0°C |
| **Temperaturschritt** | Schrittgröße für Temperaturanpassungen | 0.5°C |

### Wochenpläne

Das Modul unterstützt beliebig viele parallel laufende Wochenplan-Ereignisse für die zeitbasierte Heizungssteuerung:

- Fügen Sie einen oder mehrere Einträge zur Liste **Wochenpläne** in der Instanzkonfiguration hinzu
- Für jeden Eintrag legt das Modul unterhalb der Instanz ein Wochenplan-Ereignis mit den Aktionen **Aus**, **Komfort**, **Eco**, **Abwesend** und **Boost** an
- Die automatisch vergebene Ereignis-ID wird nach dem Speichern in die Liste zurückgeschrieben
- Schaltzeiten werden im visuellen Zeitplan-Editor (IP-Symcon-Konsole oder WebFront) des jeweiligen Ereignisses konfiguriert
- Einzelne Pläne werden über den eingebauten Schalter am Wochenplan-Ereignis aktiviert oder deaktiviert — keine Konfigurationsänderung am Modul nötig
- Pläne laufen parallel; wenn mehrere Pläne gleichzeitig schalten, gewinnt die zuletzt ausgeführte Aktion
- Wird ein Plan aus der Liste entfernt, wird das zugehörige Ereignis beim nächsten Speichern gelöscht

Temperaturvoreinstellungen (Komfort, Eco, Abwesend, Boost) werden über Variablen in der Visualisierung konfiguriert, nicht in der Instanzkonfiguration.

**Nachtabsenkung:** Bei Aktivierung überschreibt die Nachtabsenkung-Temperatur alle aktiven Pläne. Die Pläne laufen im Hintergrund weiter, aber die Absenktemperatur wird auf die Thermostate angewendet. Bei Deaktivierung wird der aktuelle Zeitplanmodus wieder angewendet.

---

## Variablen

Das Modul erstellt folgende Variablen:

| Variable | Typ | Beschreibung |
|----------|-----|--------------|
| **Solltemperatur** | Float | Aktuelle Solltemperatur für alle Thermostate |
| **Aktuelle Temperatur** | Float | Durchschnittliche Raumtemperatur aller Sensoren |
| **Heizmodus** | Integer | Aktueller Modus (Aus/Komfort/Eco/Abwesend/Boost) |
| **Komforttemperatur** | Float | Benutzer-anpassbare Komforttemperatur (Standard: 21°C) |
| **Eco-Temperatur** | Float | Benutzer-anpassbare Eco-Temperatur (Standard: 18°C) |
| **Abwesend-Temperatur** | Float | Benutzer-anpassbare Abwesend-Temperatur (Standard: 15°C) |
| **Boost-Temperatur** | Float | Benutzer-anpassbare Boost-Temperatur (Standard: 24°C) |
| **Nachtabsenkung aktiv** | Boolean | Nachtabsenkung ein-/ausschalten |
| **Nachtabsenkung-Temperatur** | Float | Temperatur bei aktiver Nachtabsenkung (Standard: 16°C) |
| **Manuelle Bedienung gesperrt** | Boolean | Bei Aktivierung werden Änderungen am Thermostat sofort zurückgesetzt |
| **Fenster offen** | Boolean | Zeigt an, ob ein konfigurierter Fensterkontakt geöffnet ist (nur lesbar) |
| **Fenster-offen-Temperatur** | Float | Temperatur bei geöffnetem Fenster (Standard: 12°C) |
| **Abwesenheitsmodus aktiv** | Boolean | Dauerhaften Abwesenheitsmodus ein-/ausschalten (verwendet Abwesend-Temperatur) |

---

## PHP-Funktionen

Das Modul stellt folgende öffentliche Funktionen für Skripte bereit:

### SetTargetTemperature

Die Solltemperatur für alle Thermostate setzen.

```php
AHC_SetTargetTemperature(int $InstanceID, float $Temperature);
```

**Parameter:**
- `$InstanceID` - ID der AdvancedHeatingControl-Instanz
- `$Temperature` - Solltemperatur in °C (innerhalb des konfigurierten Bereichs)

**Beispiel:**
```php
// Solltemperatur auf 22°C setzen
AHC_SetTargetTemperature(12345, 22.0);
```

### ApplyTemperature

Die aktuelle Solltemperatur auf alle Thermostate anwenden.

```php
AHC_ApplyTemperature(int $InstanceID);
```

**Beispiel:**
```php
AHC_ApplyTemperature(12345);
```

### SetHeatingMode

Den Heizmodus setzen.

```php
AHC_SetHeatingMode(int $InstanceID, int $Mode);
```

**Parameter:**
- `$InstanceID` - ID der AdvancedHeatingControl-Instanz
- `$Mode` - 0=Aus, 1=Komfort, 2=Eco, 3=Abwesend, 4=Boost

**Beispiel:**
```php
// Auf Eco-Modus setzen
AHC_SetHeatingMode(12345, 2);
```

### SetComfortMode / SetEcoMode / SetAwayMode / SetBoostMode / SetOff

Komfortfunktionen zum Setzen bestimmter Modi.

```php
AHC_SetComfortMode(int $InstanceID);
AHC_SetEcoMode(int $InstanceID);
AHC_SetAwayMode(int $InstanceID);
AHC_SetBoostMode(int $InstanceID);
AHC_SetOff(int $InstanceID);
```

**Beispiel:**
```php
// Auf Komfortmodus wechseln
AHC_SetComfortMode(12345);

// Auf Boost-Modus für schnelles Aufwärmen wechseln
AHC_SetBoostMode(12345);
```

### SetNightSetback

Nachtabsenkung ein- oder ausschalten.

```php
AHC_SetNightSetback(int $InstanceID, bool $Active);
```

**Parameter:**
- `$InstanceID` - ID der AdvancedHeatingControl-Instanz
- `$Active` - true zum Aktivieren, false zum Deaktivieren

**Beispiel:**
```php
// Nachtabsenkung aktivieren
AHC_SetNightSetback(12345, true);

// Nachtabsenkung deaktivieren (wendet aktuellen Zeitplanmodus wieder an)
AHC_SetNightSetback(12345, false);
```

### SetPermanentAwayMode

Dauerhaften Abwesenheitsmodus ein- oder ausschalten.

```php
AHC_SetPermanentAwayMode(int $InstanceID, bool $Active);
```

**Parameter:**
- `$InstanceID` - ID der AdvancedHeatingControl-Instanz
- `$Active` - true zum Aktivieren, false zum Deaktivieren

**Beispiel:**
```php
// Dauerhaften Abwesenheitsmodus aktivieren
AHC_SetPermanentAwayMode(12345, true);

// Dauerhaften Abwesenheitsmodus deaktivieren (wendet aktuellen Zeitplanmodus wieder an)
AHC_SetPermanentAwayMode(12345, false);
```

### GetCurrentTemperature

Die aktuelle Raumtemperatur abrufen.

```php
float AHC_GetCurrentTemperature(int $InstanceID);
```

**Rückgabe:** Aktuelle Temperatur in °C

**Beispiel:**
```php
$temp = AHC_GetCurrentTemperature(12345);
echo "Aktuelle Raumtemperatur: {$temp}°C";
```

### GetTargetTemperature

Die aktuelle Solltemperatur abrufen.

```php
float AHC_GetTargetTemperature(int $InstanceID);
```

**Rückgabe:** Solltemperatur in °C

### GetScheduleEventID

Die Ereignis-ID des ersten konfigurierten Wochenplans abrufen (aus Kompatibilitätsgründen erhalten).

```php
int AHC_GetScheduleEventID(int $InstanceID);
```

**Rückgabe:** Ereignis-ID des ersten Wochenplans, oder 0 wenn keine Pläne konfiguriert sind

### GetSchedulePlanCount

Die Anzahl der konfigurierten Wochenpläne abrufen.

```php
int AHC_GetSchedulePlanCount(int $InstanceID);
```

**Rückgabe:** Anzahl der aktuell vom Modul verwalteten Pläne

### GetSchedulePlanEventID

Die Ereignis-ID eines bestimmten Wochenplans über seinen nullbasierten Index abrufen.

```php
int AHC_GetSchedulePlanEventID(int $InstanceID, int $Index);
```

**Parameter:**
- `$InstanceID` - ID der AdvancedHeatingControl-Instanz
- `$Index` - Nullbasierter Index des Plans (0 = erster Plan)

**Rückgabe:** Ereignis-ID, oder 0 wenn der Index außerhalb des gültigen Bereichs liegt

**Beispiel:**
```php
// Über alle Wochenpläne iterieren
$count = AHC_GetSchedulePlanCount(12345);
for ($i = 0; $i < $count; $i++) {
    $eventID = AHC_GetSchedulePlanEventID(12345, $i);
    echo "Plan $i: Ereignis #$eventID" . PHP_EOL;
}
```

---

## Changelog

### Version 1.3.0
- Geändert: Lizenzwechsel von MIT zu EUPL-1.2 (siehe LICENSE für den vollständigen Text). Releases bis 1.2.0 bleiben unter MIT.
- Neu: Mehrere Wochenpläne — beliebig viele parallel laufende Pläne über die neue Liste **Wochenpläne** in der Instanzkonfiguration
- Neu: `AHC_GetSchedulePlanCount` und `AHC_GetSchedulePlanEventID` für Skript-Zugriff auf die Plan-Liste
- Geändert: Der bisherige einzelne Wochenplan wird beim ersten Speichern automatisch als Eintrag „Standard" in die neue Liste übernommen
- Geändert: `AHC_GetScheduleEventID` liefert jetzt die Ereignis-ID des ersten Plans (rückwärtskompatibel für Installationen mit einem einzigen Plan)
- Behoben: Falsches Beispiel für `AHC_SetHeatingMode` in der Dokumentation (Modus `2` ist Eco, nicht `1`)
- Behoben: Wochenplan-Ereignisse werden nicht mehr komplett neu erstellt, wenn Action-/Gruppen-Anzahl abweicht — benutzerdefinierte Schaltzeiten bleiben erhalten
- Behoben: Anzeige `Fenster offen` spiegelt beim Modulstart den tatsächlichen Kontaktzustand wider
- Behoben: Fehlgeschlagene `RequestAction`-Aufrufe an Thermostate werden ins Modul-Log geschrieben statt stillschweigend verworfen
- Behoben: Sollwert-Variablen werden bei Konfigurationsänderungen auf den aktuellen Min/Max-Bereich geclampt
- Verbessert: Variablen-Darstellungen werden über stabile GUID-Konstanten statt lokalisierter Captions gesucht
- Verbessert: Unbekannte Zeitplan-Action-IDs erzeugen eine Log-Warnung statt stillschweigend auf „Aus" zu schalten
- Verbessert: Toleranz-Schwelle gegen Echo-Effekte skaliert mit der konfigurierten Schrittgröße (korrektes Verhalten bei Integer-Thermostaten)
- Verbessert: Modul-Status meldet einen Fehler, wenn die Mindesttemperatur nicht unter der Maximaltemperatur liegt
- Entfernt: Toter `TempBeforeWindowOpen`-Attribut (wurde geschrieben, aber nie gelesen)

### Version 1.2.0
- Neu: Dauerhafter Abwesenheitsmodus (`Abwesenheitsmodus aktiv`) - wendet dauerhaft die Abwesend-Temperatur an und überschreibt den Zeitplan
- Neu: `AHC_SetPermanentAwayMode` PHP-Funktion für Skripte
- Prioritätskette: Fenster offen > Nachtabsenkung > Abwesenheitsmodus > Zeitplan

### Version 1.1.0
- Behoben: Variablenwerte (Temperaturen, Modi, Schalter) werden bei Konfigurationsänderungen (z.B. Hinzufügen eines Thermostats) nicht mehr auf Standardwerte zurückgesetzt

### Version 1.0.0
- Erstveröffentlichung
- Multi-Thermostat-Steuerung mit einheitlicher Solltemperatur
- Raumtemperaturüberwachung mit Sensor-Mittelwertbildung
- Fünf Heizmodi (Aus, Komfort, Eco, Abwesend, Boost)
- Wochenplan mit individueller Tageskonfiguration über integriertes IP-Symcon Zeitplan-Ereignis
- Nachtabsenkung-Funktion
- Fensterkontakt-Unterstützung mit automatischer Temperaturabsenkung
- Sperrung der manuellen Bedienung
- Konfigurierbarer Temperaturbereich und Schrittgröße
- Temperaturvoreinstellungen über Variablen in der Visualisierung anpassbar
- Vollständige deutsche Lokalisierung

---

## Support

Bei Problemen, Funktionswünschen oder Beiträgen besuchen Sie bitte:
- [GitHub Repository](https://github.com/mwlf01/IPSymcon-AdvancedHeatingControl)
- [GitHub Issues](https://github.com/mwlf01/IPSymcon-AdvancedHeatingControl/issues)
- [Symcon Community](https://community.symcon.de/) – Benutzer: **mwlf**

---

## Lizenz

Dieses Projekt steht unter der **European Union Public Licence (EUPL) v. 1.2** — siehe die [LICENSE](LICENSE)-Datei für den vollständigen Lizenztext.

Die EUPL ist eine Copyleft-Lizenz: abgeleitete Werke, die weitergegeben werden, müssen ebenfalls unter der EUPL oder einer kompatiblen Lizenz veröffentlicht werden (z. B. GPL, AGPL, MPL, LGPL — die vollständige Kompatibilitätsliste steht im Anhang der EUPL). Frühere Releases bis Version 1.2.0 bleiben weiterhin unter der zuvor genutzten MIT-Lizenz verfügbar.

Die EUPL wird in 24 offiziellen Sprachfassungen veröffentlicht, die rechtlich alle gleichwertig sind. Die Lizenz kann in anderen Sprachen auf der [offiziellen EU-Seite](https://interoperable-europe.ec.europa.eu/collection/eupl/eupl-text-eupl-12) eingesehen werden.

---

## Autor

**mwlf01**

- GitHub: [@mwlf01](https://github.com/mwlf01)
- Symcon Community: [mwlf](https://community.symcon.de/)
