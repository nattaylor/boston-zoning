TODO
 - Use #hash link methodology
 - Link to ParcelViewer (join on address to the permits to get parcelid)
 - Incremental build (pass new decisions or minutes)
 - Store into database
 - UI: Presentation of appeal
 - Mobile support
 - UI: navbar
 - ~Write "About"~
 - Link to minutes
 - Link to Boston City TV (https://www.youtube.com/user/BostonCable/search?query=zoning) and (https://www.cityofboston.gov/cable/video_library.asp)
 - Filter by type (e.g. show all extensions)
 - Error checking!
 - Create a dashboard of parsing successfes/failure / test cases!
 - Add cache to assessing
 - Check for dependencies (pdftohtml)
 - "Views" functionality with filtering and such
 - Note about next update
 


## Building

1. Run `php -f build.php minutes dry`
2. Print "Starting to build..."
3. Parsed ### successfully; #### Problems
4. Print "Open SpecialCases.json" to add special cases
5. Generate a new release
6. Commit with a release tag
7. `git push origin master`

