// Live test web app for Inline Google Spreadsheet Viewer
// (tests/e2e/live-google.spec.js). Deploy it from script.google.com with
// Deploy > New deployment > Web app, Execute as: Me, Who has access: Anyone,
// and put the /exec URL in IGSV_LIVE_WEBAPP.
//
//   ...exec               -> HTML fragment (as text)
//   ...exec?format=csv    -> CSV
//   ...exec?tqx=reqId:0   -> Google Visualization data (for charts)
const ROWS = [['Team', 'Goals'], ['Aliens', 5], ['Ninjas', 12], ['Pirates', 7], ['Robots', 9]];

function doGet(e) {
  const p = e.parameter || {};
  if (p.tqx) {
    return gviz_(p.tqx);
  }
  if (p.format === 'csv') {
    return ContentService.createTextOutput(ROWS.map(r => r.join(',')).join('\n'))
      .setMimeType(ContentService.MimeType.CSV);
  }
  return ContentService.createTextOutput(
    '<div class="igsv-live-webapp"><h3>Live web app</h3>' +
    '<p>Total goals: <strong>33</strong></p>' +
    '<script>window.igsvLiveWebappScript = true;</script></div>');
}

function gviz_(tqx) {
  const opts = {};
  tqx.split(';').forEach(pair => {
    const i = pair.indexOf(':');
    if (i > 0) opts[pair.slice(0, i)] = pair.slice(i + 1);
  });
  const table = {
    cols: [{ id: 'A', label: 'Team', type: 'string' }, { id: 'B', label: 'Goals', type: 'number' }],
    rows: ROWS.slice(1).map(r => ({ c: [{ v: r[0] }, { v: r[1] }] })),
  };
  const response = { version: '0.6', reqId: opts.reqId || '0', status: 'ok', table: table };
  const handler = /^[\w.$]+$/.test(opts.responseHandler || '') ? opts.responseHandler : 'google.visualization.Query.setResponse';
  return ContentService.createTextOutput(handler + '(' + JSON.stringify(response) + ');')
    .setMimeType(ContentService.MimeType.JAVASCRIPT);
}
