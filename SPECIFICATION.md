# Boston Zoning Appeal Archive Specification

Version 1.0 2019-11-14

## Section 1: Introduction

The Boston Zoning Board of Appeals (ZBA) only offers PDF meeting minutes, leaving residents wanting
for a different, more structure dataformat that would enable analysis, so this
specification was developed to support that use case.

This specification is currently implemented by Nat Taylor <nattaylor@gmail.com>
and available on the web at [https://nattaylor.com/eastboston/boston-zoning](https://nattaylor.com/eastboston/boston-zoning)

## Section 2: Methodology

The ZBA meeting minutes available at [https://www.boston.gov/departments/inspectional-services/zoning-board-appeal](https://www.boston.gov/departments/inspectional-services/zoning-board-appeal) are first converted to HTML with `pdftohtml` [http://pdftohtml.sourceforge.net/](http://pdftohtml.sourceforge.net/).
The resulting HTML is then parsed into JSON that adheres to this specification.

The specification is flexible and allows the implementation to normalize the data
by fixing typos, removing unnecessary tokens, joining with lookup tables
and summarizing phrases (etc).



## Section 3: Case Specification

Each case follows this case specification.  Currently, none of the fields are required.

|  Property  |  Type  |                                                                                                                       Description                                                                                                                        |
|------------|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| appeal     | string | The appeal ID (e.g. BOA-123456) normalized for consistency (e.g. BOA#123 → BOA-123)                                                                                                                                                                      |
| address    | string | The address, normalized to remove trailing commas etc  (e.g. "123 Fake St, " → "123 Fake St")                                                                                                                                                            |
| ward       | string | The ward from List 4.1                                                                                                                                                                                                                                   |
| applicant  | string | The applicant, normalized to remove trailing abbreviations like ", Esq" and to fix typos like "Timuthy" → "Timothy"                                                                                                                                      |
| articles   | object | A dictionary of the articles from which relief is requested of the form `ARTICLE(ARTICLE.SECTION)` (e.g. "(53(53.1)"), where the term can be defined by an array of specific phrases from the code, if present (e.g. "Excessive Building Height (feet)") |
| purpose    | string | The purpose                                                                                                                                                                                                                                              |
| discussion | string | The discussion                                                                                                                                                                                                                                           |
| testimony  | string | The testimony                                                                                                                                                                                                                                            |
| documents  | string | The documents                                                                                                                                                                                                                                            |
| vote       | string | The vote  inferred from the vote verbiage (e.g. "voted unanimously to deny" → "DENIED")                                                                                                                                                                   |
| status     | string | The appeal status from List 4.2 inferred from the raw status (e.g. when a vote approves a deferral, then "DEFERRED")                                                                                                                                      |
| parcel     | string | The GIS parcel id, if available, from joining to City Assessing data on ST_NUM and ST_NAME.                                                                                                                                                              |
| date       | date   | The date the appeal was heard                                                                                                                                                                                                                            |
| type       | string | The type of appeal, usually from List 4.3, but the raw value if the list lookup fails                                                                                                                                                                    |
|            |        |                                                                                                                                                                                                                                                          |

## Section 4: Lists

### List 4.1 Wards

| WARD | NEIGHBORHOOD(S)                                                                |
|------|--------------------------------------------------------------------------------|
| 1    | East Boston                                                                    |
| 2    | Charlestown                                                                    |
| 3    | Back Bay/Beacon Hill; Downtown/North End/South End                             |
| 4    | Back Bay/Beacon Hill; Downtown/North End/South End; Jamaica Plain/Mission Hill |
| 5    | Back Bay/Beacon Hill; Downtown/North End/South End; Fenway/Kenmore             |
| 6    | South Boston                                                                   |
| 7    | South Boston                                                                   |
| 8    | Downtown/North End/South End; North Dorchester; Roxbury/Franklin Field         |
| 9    | Downtown/North End/South End                                                   |
| 10   | Fenway/Kenmore; Jamaica Plain/Mission Hill                                     |
| 11   | Jamaica Plain/Mission Hill                                                     |
| 12   | Jamaica Plain/Mission Hill; Roxbury/Franklin Field                             |
| 13   | North Dorchester                                                               |
| 14   | South Dorchester; Mattapan; Roxbury/Franklin Field                             |
| 15   | North Dorchester; South Dorchester                                             |
| 16   | South Dorchester                                                               |
| 17   | South Dorchester; Mattapan; Roxbury/Franklin Field                             |
| 18   | Hyde Park; Mattapan; Roslindale/Moss Hill/West Roxbury                         |
| 19   | Jamaica Plain/Mission Hill; Roslindale/Moss Hill/West Roxbury                  |
| 20   | Roslindale/Moss Hill/West Roxbury                                              |
| 21   | Allston/Brighton                                                               |
| 22   | Allston/Brighton                                                               |

### List 4.2 Appeal Status

|   Status  | Description |
|-----------|-------------|
| APPROVED  |             |
| DENIED    |             |
| DISMISSED |             |
| DEFERRED  |             |

### List 4.3 Appeal Type

|         TYPE        | DESCRIPTION |
|---------------------|-------------|
| HEARING             |             |
| EXTENSION           |             |
| GCOD                |             |
| RE-DISCUSSIONS      |             |
| BOARD FINAL ARBITER |             |
