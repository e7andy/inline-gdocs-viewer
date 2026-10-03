# Bundled third-party libraries

The plugin ships these libraries unmodified so that visitors' browsers don't load them from third-party CDNs. Each folder contains the library's license. All of these licenses are compatible with GPL-3.0.

| Folder | Library | Version | License | Source |
| --- | --- | --- | --- | --- |
| `datatables/` | DataTables (core + default styling) | 3.1.3 | MIT | npm `datatables.net`, `datatables.net-dt` |
| `datatables-buttons/` | DataTables Buttons | 4.1.2 | MIT | npm `datatables.net-buttons`, `datatables.net-buttons-dt` |
| `datatables-select/` | DataTables Select | 4.1.1 | MIT | npm `datatables.net-select`, `datatables.net-select-dt` |
| `datatables-fixedheader/` | DataTables FixedHeader | 5.1.2 | MIT | npm `datatables.net-fixedheader`, `datatables.net-fixedheader-dt` |
| `datatables-fixedcolumns/` | DataTables FixedColumns | 6.1.1 | MIT | npm `datatables.net-fixedcolumns`, `datatables.net-fixedcolumns-dt` |
| `datatables-responsive/` | DataTables Responsive | 4.1.1 | MIT | npm `datatables.net-responsive`, `datatables.net-responsive-dt` |
| `jszip/` | JSZip | 3.10.2 | MIT or GPL-3.0 (dual) | npm `jszip` |
| `pdfmake/` | pdfmake | 0.3.11 | MIT | npm `pdfmake` |

`pdfmake/vfs_fonts.js` embeds the Roboto font by Google, licensed under the Apache License 2.0. Its text is in `pdfmake/LICENSE-Roboto-Apache-2.0.txt`. `pdfmake.min.js` also bundles MIT-licensed dependencies such as PDFKit.

`buttons.colVis.min.js`, `buttons.html5.min.js`, and `buttons.print.min.js` are small compatibility stubs in Buttons 4, which includes those features in `dataTables.buttons.min.js`. They are kept so that the script handles documented in `docs/reference.md` still exist.

To update a library, download the new package from npm, copy the same files and its license file over the old ones, update this table, and run the test suite.
