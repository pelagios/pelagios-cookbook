<?php 

/*
 * This is a script written for the Pelagios project to the convert the data 
 * from the ure.xml file into 
 * 1) a CSV file containing information for each object on the matching 
 * Pleiades places for that object
 * 2) an N3 file containing that information in OAC-format
 *
 * The ure.xml file contains all the information from the Ure Museum database 
 * and was supplied by the museum: 
 * http://lkws1.rdg.ac.uk/cgi-bin/ure/uredb.cgi 
 * For each object there is a set of fields such as accession
 * number, material, decoration etc. The important ones for this script are 
 * 'fabric' and 'provenance' as these contain information about places 
 * associated with the object. 
 * 
 * The Pleiades gazetteer http://pleiades.stoa.org/ is used as a reference for 
 * places - we want to find all matches in Pleiades for any places associated 
 * with each object. We use the Pleiades+ file for this which uses geonames 
 * to find alternate toponyms for places. 
 * 
 * The basic idea of the script is to take the fabric and provenance data and 
 * then go through each place name in 
 * Pleiades+ to look for matches. There are a few complications
 * - special cases turn out to be needed for various alternate toponyms not in 
 * Pleiades
 * - special cases are needed when there are multiple places with the same name 
 * to make sure that the correct one is used
 */

$start_time = time();
set_time_limit(900); // the script can take a while! 

// If the target file ure.csv exists, then delete it, then open a file with 
// that name to write the new data to. Then write the headings for the csv file 

if (file_exists('ure.csv')) {
    unlink('ure.csv');
}
$ure_data = fopen('ure.csv', 'w') or die('Cannot open ure.csv');

fwrite($ure_data, "Ure Museum URL,");
fwrite($ure_data, "Fabric,");
fwrite($ure_data, "Provenance,");
fwrite($ure_data, "Pleiades+ normalised names,");
fwrite($ure_data, "Pleiades+ IDs,");
fwrite($ure_data, "Pleiades URLs,");
fwrite($ure_data, "\n");

// If the target file ure.n3 exists, then delete it, then open a file with that 
// name to write the new data to. Then write the headers for the csv file 

if (file_exists('ure.n3')) {
    unlink('ure.n3');
}
$ure_n3_data = fopen('ure.n3', 'w') or die('Cannot open ure.n3');

fwrite($ure_n3_data, "@prefix dc: <http://purl.org/dc/elements/1.1/>.\n");
fwrite($ure_n3_data, "@prefix dcterms: <http://purl.org/dc/terms/>.\n");
fwrite($ure_n3_data, "@prefix foaf: <http://xmlns.com/foaf/0.1/>.\n");
fwrite($ure_n3_data, "@prefix oac: <http://www.openannotation.org/ns/>.\n");
fwrite($ure_n3_data, "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>.\n\n");

// If the target file ure_images.n3 exists, then delete it, then open a file 
// with that name to write the new data to. Then write the headers for the csv 
// file 

if (file_exists('ure_images.n3')) {
    unlink('ure_images.n3');
}
$ure_n3_image_data = fopen('ure_images.n3', 'w') or die('Cannot open ure_images.n3');


// Read the Pleiades+ file and put the data from it into an array
// At this point, we also deal with multiple places with the same name. We do 
// this by removing any unneeded places from our list of places. This is a bit 
// of a horrible hack but avoids having to check all of these for each object
// later on. 
$row = 1;
$pleiades_unused_ids = array(    240868, //Attica
                                580100, 580101, // Salamis
                                981516, 991392, // Cyprus
                                39017, 511343, 619226, //Olympus
                                991374 , 1001895, //Thessaly
                                462215, //Gela 
                                550496, 606283, //Chios 
                                981503, // Aegyptus 
                                727082, //Babylon
                                570335 , //Iasos
                                462283, // Lipari
                                590030, // Rhodos
                                991350, //Campania
                                540783, // Chalcis
                                658377, // Amphipolis
                                550893, //Smyrna
                                29457 , 59668, 59669, 59675, 59694, 60406, 60409, 60410, 876562,912872, 961873, 
                                //Alexandria
                                474919, 511271, 536081, 570288, 599641, 521029, //Heraion
                                501325, // Abydos
                                413058, //Cales
                                229596, //Teos
                                543777, 674250, //Megara 
                                711220, 631184, 619114, 599516, 595699, 589708, 573117, 554192, 491527, //Argos 
                                573200, //Elis
                                543732, //Ionia 
                                981517, //Cyrene
                                741517, //Memphis 
                                991385, 1001909, //Lydia 
                                501596, //Samothrace
                                501634, //Thasos 
                                97304, //Histria
                                246359, //Cortona
                                599867, //Paros 
                                599925, //Samos 
                                438767, 543766, 668292, 550683, 631221, //Larissa
                                981503, //Aegyptus
                                991353, //Sicily,
                                727126, // Hierakonpolis
                                737069, 759654, //Tanis
                                707556, //Larnaca,
                                570221, //Elis
                                930250, //Tanagra 
                                579925, //Eretria 
                                981531, 991368, //Macedonia 
                                481844, //Germania
                                62283, //Lipara
                                981549, //Sicily
                                );
if (($handle = fopen("pleiadesplus.csv", "r")) !== FALSE) {
    $row_number = 0;
    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($row); // number of fields in the row
        $row_number++;
        $pleiades_id = $row[0];
        $normalised_name = $row[1];
        
        $normalised_name = str_ireplace('(', '', $normalised_name);
        $normalised_name = str_ireplace(')', '', $normalised_name);
        // Remove any empty place names as otherwise they will match everything
        if (strlen(trim($normalised_name)) != 0  && !in_array($pleiades_id, $pleiades_unused_ids)) {
            $pleiades[$row_number] = array();
            $pleiades[$row_number]["normalised_name"] = $normalised_name;
            $pleiades[$row_number]["pleiades_id"] = $pleiades_id;
        } else {
            $row_number--;
        }
    }
    $total_rows = $row_number;
    fclose($handle);
}

echo "Read Pleiades+ data<br />";

// Get all the data from the XML file
$xmlstr = file_get_contents('ure.xml');
$collection = simplexml_load_string($xmlstr);
$data = $collection->data;
// Loop through the data from the XML file, clean it as necessary then search the Pleiades data for any matches
// and write the results to the csv and n3 files. 
$timestamp = time();
foreach($data->record as $record) {
    if (strlen(trim($record->Fabric)) || strlen(trim($record->Provenience))) {
        
        fwrite($ure_data, "\""."http://lkws1.rdg.ac.uk/cgi-bin/ure/uredb.cgi?rec=".$record->Accession_Number . "\",");
        fwrite($ure_data, "\"".$record->Fabric . "\",");
        fwrite($ure_data, "\"".$record->Provenience  . "\",");
        
        $clean_provenance = clean_provenance($record->Provenience);
        $clean_fabric       = convert_fabric($record->Fabric);
        $result           = search_pleiades($clean_provenance . " ".$clean_fabric, $pleiades);
        
        fwrite($ure_data, "\"".implode(' ', $result["names"]). "\","); // Turn the array of names into a string
        fwrite($ure_data, "\"".implode(' ', $result["ids"]). "\","); // Turn the array of IDs into a string
        
        // Turn the array of IDs into a list of Pleiades URLs 
        $urls = "";
        foreach($result["ids"] as $id) {
            $urls .= "http://pleiades.stoa.org/places/".$id." ";
        }
        
        fwrite($ure_data, "\"".$urls . "\",");
        fwrite($ure_data, "\n");
        
        foreach($result["ids"] as $id) {
            $ref = "<http://uredb.reading.ac.uk/ure/ure.n3#".$timestamp."/".$record->Accession_Number."_".$id.">";

            fwrite($ure_n3_data, "$ref  rdf:type oac:Annotation .\n");
            fwrite($ure_n3_data, "$ref  oac:hasBody <http://pleiades.stoa.org/places/$id/#this> .\n");
            fwrite($ure_n3_data, "$ref  oac:hasTarget <http://uredb.reading.ac.uk/cgi-bin/ure/uredb.cgi?rec=".$record->Accession_Number."> .\n");
            fwrite($ure_n3_data, "$ref dcterms:title \"".$record->Accession_Number.": ".$record->Shape.' '.$record->Period."\" .\n");
            /*$image_field = $record->Image;
            if ($image_field && $record->Accession_Number) {
                $images = $image_field->children();
                if (isset($images) && count($images) > 0) {
                    $image = $images[0]; // Only include the first iamge
                        $src = $image->src;
                        //$directory = 
                          //  fwrite($ure_n3_data , "<http://lkws1.rdg.ac.uk/cgi-bin/ure/uredb.cgi?rec=".$record->Accession_Number."> 
                          //                           foaf:thumbnail 
                          //                           <http://lkws1.rdg.ac.uk/ure/pixdir/".$record->Accession_Number."/thumb/".$src."> .\n");

                }
            }*/
            fwrite($ure_n3_data, "$ref dcterms:created ".date("\"Y-m-j\TH:i:s\Z", $timestamp)."\"^^<http://www.w3.org/2001/XMLSchema#dateTime> .\n\n");
        }
    }
}
/**
 * Search for a string in the Pleiades data
 * @string string The string to look for matches in
 * @array The array of Pleiades names 
 * Returns an array containing two arrays - one of all the matching names and one of all the matching Pleiades IDs. 
 */
function search_pleiades($string, $pleiades) {
    $names = array();
    $ids = array();
    
    // Loop through each place in the Pleiades array and look for matches
    foreach($pleiades as $pleiades_place) {
        $normalised_name = $pleiades_place["normalised_name"];
        if (preg_match( "@\b".$normalised_name."\b@i", $string)) {
            $names[] = trim($normalised_name);
            $ids[] = $pleiades_place["pleiades_id"];
        }
    }

    // Remove duplicates 
    $names  = array_unique($names);
    $ids      = array_unique($ids);
    
    // There are  a couple of special cases. 
    // There are two 'Thebes' - one in Egypt and one in Greece
    if (in_array('thebes', $names)) {
        if (in_array('aegyptus', $names)) {
            unset($ids[array_search('541138', $ids)]);
        } else {
            unset($ids[array_search('786017', $ids)]);
        }        
    }
    // Another special case for Sparta/Laconia as Laconia matches Sparta but there are also objects associated with 
    // Laconia 
    if (in_array('sparta', $names) && !in_array('laconia', $names)) {
        unset($ids[array_search('570406', $ids)]);    
    }
    
    // If the number of names doesn't match the number of IDs print the details for debug purposes 
    if (count($names) != count($ids)) {
        echo implode(' ', $names).' '.implode(' ',$ids).'<br/>';
    }
    
    $result = array();
    $result["names"] = $names;
    $result["ids"] = $ids;;
    return $result;
}

/**
 * Function to strip out and replace information in the Provenance field that results in incorrect or missing 
 * matches.
 * @string provenance The provenance field to clean
 * Returns the cleaned provenance string
 */
function clean_provenance($provenance) {
    // Remove any part of the provenance field needed. Main things are 
    // 1) Remove punctuation - as matches are missed if punctuation is included
    // 2) Remove single letters as there are some weird Pleiades entries with single letter names 
    // 3) Remove any nouns which result in incorrect hits e.g. don't want 'Temple of Aphrodite' to match the 
    // place 'Aphrodite' or 'Eleusis Museum' to match 'Eleusis'. 
    $remove = array('(', ')', ';', 'T.', 'P.N', 'A.D', '.',':',', ', '?', 
                    'Artemis',
                    'Orthia',
                    'Aphrodite',
                    'Athena',
                    'Acropolis Museum, Athens', 
                    'c,d,e+f', 'a+b', 'c+d','e+f', 'JHS', 'BC', 
                    'Acropolis Museum', 
                    'Eleusis Museum', 
                    'East Greece', 
                    'Egypt Exploration Society', 
                    'Eleusis Mueum',
                    'Diospolis');

    foreach ($remove as $remove_item) {
        $provenance = str_ireplace($remove_item, '', $provenance);
    }

    // Now do replacements to make sure we get all correct matches. Main categories are 
    // 1) Turning adjectives into nouns
    // 2) Alternate toponyms missing from Pleiades+. Many of these come from the Barrington Atlas Notes on 
    // Pleiades. 
    // 3) Some typos in the database
    // 4) Some places not in Pleiades we can map to wider areas 
    $replace = array(
                        'Greek'     => 'Greece',   // Adjective -> Noun
                        'Spartan'   => 'Sparta',   // Adjective -> Noun
                        'Boeotian'  => 'Boeotia',  // Adjective -> Noun
                        'Theban'    => 'Thebes',   // Adjective -> Noun
                        'Campanian' => 'Campania', // Adjective -> Noun
                        'Poli '     => 'Polis ',   // Adjective -> Noun
                        'Persian'   => 'Persia',   // Adjective -> Noun
                        'Argive'    => 'Argolis',  // Adjective -> Noun
                        'Nubian'    => 'Nubia',    // Adjective -> Noun
                        'Ionian'    => 'Ionia',    // Adjective -> Noun
                        'Argolid'   => 'Argolis',  // Adjective -> Noun

                        'Egypt'       => 'Aegyptus',        //Barrington Atlas Notes 
                        'Rhitsona'    => 'Mykalessos',      //Barrington Atlas Notes 
                        'Orvieto'     => 'Velzna',          //Barrington Atlas Notes
                        'Cerveteri'   => 'Caere',           //Barrington Atlas Notes
                        'Populonia'   => 'Fufluna',         //Barrington Atlas Notes 
                        'Keratea'     => 'Kephale',         //Barrington Atlas Notes
                        'Canosa'      => 'Canusium',        //Barrington Atlas Notes
                        'Ratisbon'    => 'Reginum',         //Barrington Atlas Notes - Regensburg
                        'Nimrod'      => 'Kalhu',           //Barrington Atlas Notes
                        'Beni Hassan' => 'Poisarietemidos', //Barrington Atlas Notes
                        'Akhmim'      => 'Panopolis',       //Barrington Atlas Notes
                        'Keratia'     => 'Kephale',         // Unconfirmed. Barrington Atlas Notes: E Keratea 
                        'Zoan'        => 'Tanis',           // Confirmed by Amy
                        'Kom Ushim'   => 'Karanis',         // Unconfirmed - Barrington Atlas Notes: 
                                                            // Kom Awshim
                        'Cnidus'      => 'Knidos',          // Cyprus location suggests Knidos


                        'Naukratis' => 'Naucratis', // Transliteration, confirmed by Amy
                        'Daphnae'   => 'Daphnai',   // Transliteration, confirmed by Amy
                        'Meydoum'   => 'Meidum',    // Transliteration, confirmed by Amy
                        'Hu'        => 'Hiw',       // Transliteration, confirmed by Amy
                        'Khorsiai'  => 'Chorsiai',  // Transliteration, confirmed by Amy


                        'Rhodes'  => 'Rhodos',   // Well-known, confirmed by Amy
                        'Gaul'    => 'Gallia',   // Well-known
                        'Spain'   => 'Hispania', // Well-known
                        'Germany' => 'Germania', // Confirmed by Amy
                        'Tunisia' => 'Carthago', // Confirmed by Amy
                        
                        'al Mina'               => 'Almina',
                        'Lepcis Magna'          => 'LepcisMagna',
                        'La Graufesenque'       => 'LaGraufesenque',
                        'Asia Minor'            => 'AsiaMinor',
                        'Magnesia ad Maeandrum' => 'MagnesiaAdMaeandrum',
                        
                        'Gurob' => 'KomMedinetGhurab', // Confirmed by Amy
                        'Gurab' => 'KomMedinetGhurab', //Confirmed by Amy
                        
                        'Kopinth'   => 'Corinth',   // Typo according to Amy
                        'ANKARD'    => 'Gordion',   // Mistake in database,
                        'Olympus'   => 'Olynthos',  // Typo according to Amy
                        'Cyreraica' => 'Cyrenaica', // Typo according to Amy
                        
                        'Qurneh' => 'Thebes',
                        'Enkomi' => 'Cyprus', // not in Pleiades
                        'Achna'  => 'Cyprus' // not in Pleiades 
                        );
                        
    foreach ($replace as $item => $replace_item) {
        $provenance = str_ireplace($item, $replace_item, $provenance);
    }

    // Get rid of any numbers and any lower case words, making the assumption that all place names will be 
    // captitalised. 
    $provenance = preg_replace('@[0123456789]@', '', $provenance);
    $provenance = preg_replace('@\s[a-z][a-z]*@', ' ', $provenance);
    $provenance = preg_replace('@\^[a-z][a-z]*@', ' ', $provenance);
    $provenance = trim($provenance);
    return $provenance;
}

/**
 * Convert the fabric name into the appropriate place name. 
 */
function convert_fabric($fabric) {
    $fabric = str_ireplace('(?)', '', $fabric);
    
    $fabrics = array(
                     'East Greek' => '',
                     'Protocorinthian' => 'Corinth',
                     'Corinthian' => 'Corinth',
                     'Boeotian' => 'Boeotia',
                     'Attic' => 'Attica',    
                     'Etruscan' => 'Etruria',
                     'Cypriote' => 'Cyprus',
                     'Cypriot' => 'Cyprus',
                     'Egyptian' => 'Aegyptus',
                     'Thessalian' => 'Thessaly',
                     'Cretan' => 'Crete',
                     'Minoan' => 'Crete',
                     'Mycenaean' => 'Mycenae',
                     'Chiot' => 'Chios',
                     'Laconian' => 'Laconia',
                     'Lucanian' => 'Lucania',
                     'Apulian' => 'Apulia',
                     'Paestan' => 'Paestum',
                     'Gnathian' => 'Gnathia',
                     'Daunian' => 'Daunia',
                     'Rhitsona' => 'Rhitsona',
                     'Athenian' => 'Athens',    
                     'Greek' => 'Greece',    
                     'Rhodian' => 'Rhodos',    
                     'Italian' => 'Italy',    
                     'Euboean' => 'Euboea',    
                     'Aeolic' => 'Aeolis',
                     'Tanagra' => 'Tanagra',    
                     'African' => 'Africa',    
                     'Alexandrian' => 'Alexandria',    
                     'Argive' => 'Argos',        
                     'Calenian' => 'Cales',    
                     'Canosan' => 'Canosa',    
                     'Carthaginian' => 'Carthage',    
                     'Cnidian' => 'Knidos',
                     'Cyrenaican' => 'Cyrenaica',                     
                     'Eretrian' => 'Eretria',
                     'Faliscan' => 'Etruria',    
                     'Gaulish' => 'Gaul',    
                     'German' => 'Germany',    
                     'Kasnit' => '',    
                     'Megarian' => 'Megara',    
                     'Messapian' => 'Messapia',    
                     'Metapontine' => 'Metapontum',    
                     'Naucratite' => 'Naucratis',    
                     'Romanian' => 'Romania',    
                     'Romano-egyptian' => 'Egypt',    
                     'Samian' => 'Samos',    
                     'Sicilian' => 'Sicily',
                     'Spartan' => 'Sparta',    
                     'Campanian' => 'Campania',    
                     'Tunisian' => 'Tunisia',    
                     'Roman' => 'Rome',
                     'Sub-mycenaean' => 'Mycenae',    
                     'Tarentine' => 'Tarentum',    
                     'Tunisia/Algeria' => 'Tunisia Algeria',
                     'Camiran' => 'Kamiros',        
                     'Clazomenian' => '',            
                     'Dhitsa (Dilisi)' => 'Dhitsa',
                     'Romano-Egyptian' => 'Aegyptus',    
                     'Nubian' => 'Nubia',
                     'Ratisbon' => 'Reginum',
                     'Ionian' => 'Ionia',
                     'Tunisia' => 'Carthago',
                     'Germany' => 'Germania',
                     'Gaul' => 'Gallia',
                     'Asia Minor' => 'AsiaMinor',
                        );

    foreach ($fabrics as $entry =>$location) {
        $fabric = str_ireplace($entry, $location, $fabric);
    }
    $fabric = preg_replace('@\s[a-z][a-z]*@', ' ', $fabric);
    return $fabric;
}

$end_time = time();
$time_taken = $end_time -$start_time;
echo "Finished took $time_taken s";
?>