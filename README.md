# Enhanced Zoning Decisions

Enhance Boston Zoning decisions with structure and additional data

TODO
 - Bug: check for deferral in the discussion
 - CRON to check for updates --> view-source:https://www.boston.gov/departments/inspectional-services/zoning-board-appeal --> `<div class="brc-lu">      Last updated:   <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-11-12T14:45:00-05:00">11/12/19</span>    </div>`  --> send email if that's today
 - Feature: Finish lookup-variances.json https://docs.google.com/spreadsheets/d/1o0yVtxE9DFupkGMdXybgTgIXH1XvWGJUZ3Do3G7XLyc/edit#gid=0
 - Refactor: Add code comments
 - Refactor: specialCases to lookup-cases.json
 - Manually convert old minutes ☹️
 - Bug: Fix BOA-678322
 - Feature: clean by implementing html_entity_decode
 - New property: Link to minutes
 - New property: Link to Boston City TV (https://www.youtube.com/user/BostonCable/search?query=zoning) and (https://www.cityofboston.gov/cable/video_library.asp)
 - Build: List the results of parsing successfes/failure / test cases!
 - Build: Pass build directory to build script
 - Full address (ward to zip, ward to neighborhood, zip to neighborhood)
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

Web UI
- UI: navbar
- UI: change to panels
- Filter by type (e.g. show all extensions)
- Check for dependencies (pdftohtml)
- "Views" functionality with filtering and such
- New property: Link to ParcelViewer (join on address to the permits to get parcelid) ()
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
