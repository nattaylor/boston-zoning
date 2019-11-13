# Enhanced Zoning Decisions

Enhance Boston Zoning decisions with structure and additional data

TODO
 - Fix BOA-678322
 - Implement html_entity_decode
 - New property: Link to minutes
 - New property: Link to Boston City TV (https://www.youtube.com/user/BostonCable/search?query=zoning) and (https://www.cityofboston.gov/cable/video_library.asp)
 - New property: Link to ParcelViewer (join on address to the permits to get parcelid) ()
 - CRON to check for updates
 - Pass build directory to build script
 - Do decisions full outer join minutes
 - Add search
 - Add (chain) icon for deeplinking
 - "View on the web"
 - Full address (ward to zip, ward to neighborhood, zip to neighborhood)
 - UI: change to panels
 - Store into database
 - Error checking!
 - Create a dashboard of parsing successfes/failure / test cases!
 - Join to property history
 - ~Note about next update~
 - ~Use #hash link methodology~
 - ~Write "About"~
 - ~Build script~
 - ~Finish downloads~
 - ~New property: parse articles~
 - ~Normalize types (extension/EXTENSION etc)~
 - ~Fix addresses with commas~
 - ~New field: normalized applicants (remove ", Esq" etc)~

Webapp
- UI: navbar
- Filter by type (e.g. show all extensions)
- Check for dependencies (pdftohtml)
- "Views" functionality with filtering and such


## Refresh Process

```
cd cache && rm zoning-board-appeal-decisions && wget 'https://www.boston.gov/departments/inspectional-services/zoning-board-appeal-decisions'
php build.php
scp -P 4452 dist/decesions_* taylorwe@taylorwebdesignshost.com:~/www/nattaylor.com/eastboston/boston-zoning
vi ~/www/nattaylor.com/.htaccess
```
