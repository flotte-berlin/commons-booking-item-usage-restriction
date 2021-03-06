# Commons Booking Item Usage Restriction

**Contributors:** poilu  
**Donate link:** https://flotte-berlin.de/mitmachen/unterstuetzt-uns/  
**Tags:** booking, commons, admin  
**Tested:** Wordpress > 4.9.6, Commons Booking 0.9.4.2  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

---
## Description

**Commons Booking Item Usage Restriction** is a Wordpress plugin which extends the [Commons Booking](https://github.com/wielebenwir/commons-booking) plugin by the option to manage usage restrictions for items. This can be a total breakdown (item can't be used at all) or a limitation (item is damaged, but can be used nevertheless). When a usage restriction is created all users that have a booking for the item in the given period, which isn't over at time of creation, will be informed. A list of email adresses can be committed to send additional messages.  
When the restriction type is set to 'total breakdown' a booking for a user which was  defined in the settings before will be created to block further bookings of other users.  
For every restriction you have to provide a hint which can be inserted in the mentioned email (by template tag) and also appears in the item description (cb-items page) as long as the restriction period lies in the booking horizon.
For all items a detailed history of usage restrictions can be requested. The existing restrictions can be shortened/extended or deleted, whereby users for which the update/deletion is of interest will be informed.

## Beschreibung

**Commons Booking Item Usage Restriction** ist ein Wordpress Plugin, welches das [Commons Booking](https://github.com/wielebenwir/commons-booking) Plugin um die Möglichkeit ergänzt, Nutzungsbeschränkungen für Artikel zu verwalten. Dabei kann es sich um einen Totalausfall (Artikel ist überhaupt nicht nutzbar) oder eine eingeschränkte Nutzung (Artikel ist beschädigt, kann jedoch verwendet werden) handeln. Beim Anlegen einer Nutzungsbeschränkung werden alle NutzerInnen per Email informiert, die im angegebenen Beschränkungszeitraum eine Buchung für den Artikel angelegt haben und zum Zeitraum der Beschränkungserstellung noch nicht vorbei ist. Es kann eine Liste von Email-Adressen übergeben werden, an die zusätzlich eine Nachricht versendet werden soll.  
Bei einem Totalausfall wird für den angegebenen Beschränkungszeitraum eine Buchung für einen in den Einstellungen definierten User angelegt, um weitere Buchungen durch NutzerInnen zu verhindern.  
Zu jeder Beschränkung muss ein Hinweis angegeben werden, welcher sowohl in die Email eingefügt werden kann (Template Tag), als auch in der Artikelbeschreibung (cb-items Seite) erscheint, sofern die Beschränkung innerhalb des Buchungshorizonts liegt.  
Zu allen Artikeln kann eine detaillierte Historie der jeweiligen Nutzungsbeschränkungen angezeigt werden. Diese können dort auch verkürzt/verlängert oder gelöscht werden. NutzerInnen für welche die Änderung/Löschung relevant ist, werden benachrichtigt.

## Screenshots

![Einstellungen](/screenshots/settings_0.2.7_de.png?raw=true "Einstellungen")

![Administration](/screenshots/restrictions_0.2.5_de.png?raw=true "Administration")

![Beispiel](/screenshots/example_0.2.0_de.png?raw=true "Beispiel")

## Changelog

### 0.2.8

  * fixed check for overlapping restrictions of type 'total breakdown'  
  * changed item sorting to order by name (instead of creation date)

### 0.2.7

  * show initial restriction hint in detail view  
  * added option to show all hints (updates also) in item page

### 0.2.6

  * add deletion with comment to change history  
  * changed behaviour of booking confirmation for shortened restriction
  * check if user exists in in restriction list template (is not deleted)

### 0.2.5

  * keep removed restrictions in a "deleted" list
  * added option to list removed restrictions and show the details

### 0.2.4

  * set/reset blocked booking status for subsequently created or edited total breakdowns

### v0.2.3

  * added cron job that regularly checks bookings blocked by total breakdowns and sets status accordingly (helpful for statistical analysis)

### v0.2.1

  * only consider confirmed bookings, not pending ones on decision who has to be informed about restriction
  * added check, if an item is restricted in a given period - used in plugin [Commons Booking Post Booking](https://github.com/flotte-berlin/commons-booking-post-booking)

### v0.2.0

  * added option to shorten or extend the end date of existing usage restrictions, introduced additional email template for updates
  * cleaned up UI of administration page
    * button icons
    * removed data from restriction list table and added dialog to view details and update history of existing restrictions
  * restrictions can now overlap (only total breakdowns can't exist parallelly)
  * fixed translation issue (language path defintion)
