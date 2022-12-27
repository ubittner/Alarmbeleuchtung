# Alarmbeleuchtung

Zur Verwendung dieses Moduls als Privatperson, Einrichter oder Integrator wenden Sie sich bitte zunächst an den Autor.

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.  
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.  
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.  
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.


### Inhaltsverzeichnis

1. [Modulbeschreibung](#1-modulbeschreibung)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Schaubild](#3-schaubild)
4. [Auslöser](#4-auslöser)
5. [Externe Aktion](#5-externe-aktion)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)
    1. [Alarmbeleuchtung schalten](#61-alarmbeleuchtung-schalten)


### 1. Modulbeschreibung

Dieses Modul schaltet mittels einer Variable eine Alarmbeleuchtung in [IP-Symcon](https://www.symcon.de).

### 2. Voraussetzungen

- IP-Symcon ab Version 6.1

Sollten mehrere Variablen geschaltet werden, so sollte zusätzlich das Modul Ablaufsteuerung genutzt werden.

### 3. Schaubild

```
                       +--------------------------+
Auslöser <-------------+ Alarmbeleuchtung (Modul) |<------------- externe Aktion
                       |                          |
                       | Alarmbeleuchtung         |
                       |                          |
                       +-----------+---+----------+
                                   |  |
                                   |  |    +-------------------------+
                                   |  +--->| Ablaufsteuerung (Modul) |
                                   |       +------------+------------+
                                   |                    |
                                   |                    |
                                   v                    |
                             +----------+               |
                             | Variable |<--------------+
                             +----------+
```

### 4. Auslöser

Das Modul Alarmbeleuchtung reagiert auf verschiedene Auslöser.  

### 5. Externe Aktion

Das Modul Alarmbeleuchtung kann über eine externe Aktion geschaltet werden.  
Nachfolgendes Beispiel schaltet die Alarmbeleuchtung ein.

> ABEL_ToggleAlarmLight(12345, true);

### 6. PHP-Befehlsreferenz

#### 6.1 Alarmbeleuchtung schalten

```
boolean ABEL_ToggleAlarmLight(integer INSTANCE_ID, boolean STATE);
```

Konnte der Befehl erfolgreich ausgeführt werden, liefert er als Ergebnis **TRUE**, andernfalls **FALSE**.

| Parameter     | Wert  | Bezeichnung    |
|---------------|-------|----------------|
| `INSTANCE_ID` |       | ID der Instanz |
| `STATE`       | false | Aus            |
|               | true  | An             |

Beispiel:
> ABEL_ToggleAlarmLight(12345, false);
