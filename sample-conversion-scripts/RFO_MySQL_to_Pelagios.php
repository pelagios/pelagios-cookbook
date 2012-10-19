<?php
/* export_xml_pelagios.php 
 * RFO places with Pleiades identifier
 * http://www.francia.ahlfeldt.se/downloads/rfo_pelagios.n3 
 */
header("Content-Type: text/plain; charset=UTF-8");

require("login.php");
$select="SELECT 
			ort.id, 
			ort.name, 
			ort.cc, 
			ort.pleiades";

$from=" FROM ort";
$order=" ORDER BY ort.name";
$where=" WHERE ort.pleiades > 0";

$result = mysql_query($select .$from .$where);	
if (!$result)
  return(false);
$num=mysql_num_rows($result);
$num=1; 
while($row = mysql_fetch_row($result)) {
  $id       = $row[0];
	$oname    = $row[1];
	$cc       = $row[2];
	$pleiades = $row[3];
  echo"<http://www.francia.ahlfeldt.se#set1/annotation$num> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.openannotation.org/ns/Annotation> .\n";
  echo"<http://www.francia.ahlfeldt.se#set1/annotation$num> <http://purl.org/dc/terms/title> \"$oname\" .\n";
  echo"<http://www.francia.ahlfeldt.se#set1/annotation$num> <http://www.openannotation.org/ns/hasBody> <http://pleiades.stoa.org/places/$pleiades#this> .\n";
  echo"<http://www.francia.ahlfeldt.se#set1/annotation$num> <http://www.openannotation.org/ns/hasTarget> <http://www.francia.ahlfeldt.se/places/$id> .\n";
  $num++;
}
?>
