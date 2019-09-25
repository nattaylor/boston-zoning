<?php
include('DecisionsParser.php');

function genTable() {
	$decisionParser = new DecisionParser();
	$decisions = $decisionParser->main();
	$rows = $decisions['data'];
	$headers = "<tr><th>".implode("</th><th>",array_map('ucfirst',$decisions['headers']))."</th></tr>".PHP_EOL;

	$rows = array_map(
		function($row) {
			return "<tr onclick=\"search('".$row[2]."')\"><td>".implode("</td><td>",$row)."</td></tr>";
		}, $rows);
	return "<table id=\"decisions-table\"><thead>$headers</thead><tbody>".implode("\n", $rows)."</tbody></table>";
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Boston Zoning Board of Appeal Decisions</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script src="dist/cases.js"></script>
	<script>
		function search(appeal) {
			_case = cases.filter(_case => _case.appeal == appeal , appeal)[0];
			var div = document.createElement('div');
			div.classList.add('lightbox');
			div.id = _case.appeal;
			div.innerHTML = "<div><pre style=\"white-space: pre-wrap;\">"+JSON.stringify(_case, null, 2)+"</pre></div>";
			document.body.appendChild(div);
			document.location.hash = _case.appeal;
		}

		window.addEventListener('keyup',function(e){
			if(e.code == 'Escape') {
				document.querySelector('.lightbox').remove();
				document.location.hash = "#"
			}
		});

		window.addEventListener('DOMContentLoaded', (event) => {
				if(document.location.hash.length > 1) {
					search(document.location.hash.slice(1));
				}
		});
	</script>
	<style>
div.lightbox {
	display: none;
	position: fixed;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	z-index: 5;
	}

div.lightbox:target {
	display:block;
	bottom: 0;
	right:0;
	text-align: center;
}

.lightbox::before {
	content: "";
	display: block;
	position: fixed;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: #000000;
	opacity: 0.85;
	z-index: -2;
}

.lightbox pre {
	background-color: white;
}

.h2 {
	font-size: 1.5em;
	margin-block-start: 0.83em;
	margin-block-end: 0.83em;
	margin-inline-start: 0px;
	margin-inline-end: 0px;
	font-weight: bold;
}

#decisions-table, #decisions-table th, #decisions-table td {
	border-collapse: collapse;
	border:1px solid black;
}


</style>

</head>

<body>
<h1>Boston Zoning Board of Appeal Decisions</h1>

<p>This data is derived from the information available at <a href="https://www.boston.gov/departments/inspectional-services/zoning-board-appeal-decisions">https://www.boston.gov/departments/inspectional-services/zoning-board-appeal-decisions</a>.
<div>
	
</div>
<details>
	<summary class="h2">About</summary>
	<p>This data was last updated on <?php echo strftime("%D"); ?>.  Attempts are made to update the data in a timely matter after new zoning board minutes are posted.</p>
	<p>For more information, contact Nat Taylor &lt;<a href="mailto:nattaylor@gmail.com">nattaylor@gmail.com</a>&gt;.</p>
	<p>This data was parsed to simplify the working with Boston's zoning appeals data.  The parsing methodology is described in <a href="https://nattaylor.com/blog/2018/zoning/">this blog</a> and the code is <a href="">open source</a>.</p>
	<p>This data is made available under the Public Domain Dedication and License v1.0 whose full text can be found at: <a href="http://www.opendatacommons.org/licenses/pddl/1.0/">http://www.opendatacommons.org/licenses/pddl/1.0/</a></p>
	<p>This data is provided "as is", without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose and noninfringement. In no event shall the authors be liable for any claim, damages or other liability, whether in an action of contract, tort or otherwise, arising from, out of or in connection with the data or the use or other dealings in the data.</p>
</details>
<h2>Decisions</h2>
<p>Download CSV or Excel or Open Google Sheets</p>
<?php echo genTable(); ?>
</body>
</html>
