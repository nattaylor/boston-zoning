# Enhanced Zoning Decisions

Enhance Boston Zoning decisions with structure and additional data

TODO
 - Bug: check for deferral in the discussion
 - Refactor: Add code comments
 - Manually convert old minutes ☹️
 - Bug: Fix BOA-678322
 - Feature: clean by implementing html_entity_decode
 - New property: Link to Boston City TV (https://www.youtube.com/user/BostonCable/search?query=zoning) and (https://www.cityofboston.gov/cable/video_library.asp)
 - Build: List the results of parsing successfes/failure / test cases!
 - Build: Pass build directory to build script
 - Build: Store into database
 - Build: Error checking!
 - Feature: Join to property history
 - ~Note about next update~
 - ~Use #hash link methodology~
 - ~Write "About"~
 - ~Build script~
 - ~Finish downloads~
 - ~New property: parse articles~
 - ~Normalize types (extension/EXTENSION etc)~
 - ~Fix addresses with commas~
 - ~New field: normalized applicants (remove ", Esq" etc)~
 - ~Do decisions full outer join minutes~ No, they are the same
 - ~Refactor: specialCases to lookup-cases.json~
 - ~Full address (ward to zip, ward to neighborhood, zip to neighborhood)~ Must have meant for easier Maps searching
 - ~New property: Link to minutes~ we should construct this from the hearing date
 - ~CRON to check for updates~
 - ~Feature: Finish lookup-variances.json~

Web UI
- Deeplink to parcel viewer
- Deeplink to minutes
- UI: navbar
- UI: change to panels
- Filter by type (e.g. show all extensions)
- "Views" functionality with filtering and such
- Add search
- Add (chain) icon for deeplinking
- "View on the web"


## Decisions Refresh Process

```
cd cache && rm zoning-board-appeal-decisions && wget 'https://www.boston.gov/departments/inspectional-services/zoning-board-appeal-decisions'
php build.php
scp -P 4452 dist/decesions_* taylorwe@taylorwebdesignshost.com:~/www/nattaylor.com/eastboston/boston-zoning
vi ~/www/nattaylor.com/.htaccess
```
