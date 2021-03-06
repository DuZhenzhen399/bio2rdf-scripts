<?php
/**
Copyright (C) 2013 Jose Cruz-Toledo

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

/**
 * MeSH Gene RDFizer
 * @version 1.0
 * @author Jose Cruz-Toledo
 * @author Jose Miguel Vives
 * @author Michel Dumontier
 * @description This parser transforms the 2013 MeSH ASCII data to Bio2RDF compliant Linked Data. 
 * To download the original data files please visit http://www.nlm.nih.gov/cgi/request.meshdata
 * and register.
*/


require_once(__DIR__.'/../../php-lib/bio2rdfapi.php');
class MeshParser extends Bio2RDFizer{
	private static $packageMap = array(
		"descriptors" => "dYEAR.bin",
		"qualifiers" => "qYEAR.bin",
		"supplementary" => "cYEAR.bin"					
	);
	private static $descriptor_data_elements = array(
		"AN" =>	"annotation",
		"AQ" =>	"allowable-topical-qualifiers",
		"CATSH" => "cataloging-subheadings-list-name",
		"CX"	=> "consider-also-xref",
		"DA"	=>	"date-of-entry",
		"DC"	=> "descriptor-class",
		"DE"	=> "descriptor-entry-version",
		"DS"	=>	"descriptor-sort-version",	
		"DX"	=>	"date-major-descriptor-established",
		"EC"	=>	"entry-combination",
		"PRINT ENTRY"	=> "entry-term",
		"ENTRY"	=> "entry-term",
		"FX" =>	"forward-xref",
		"GM" =>	"grateful-med-note",
		"HN" => "history-note",
		"MED" => "backfile-posting",
		"M94" => "backfile-posting",
		"M90" => "backfile-posting",
		"M85" => "backfile-posting",
		"M80" => "backfile-posting",
		"M75" => "backfile-posting",
		"M66" => "backfile-posting",
		"MH" =>	"mesh-heading",
		"MH_TH" =>	"mesh-heading-thesaurus-id",
		"MN" =>	"mesh-tree-number",
		"MR" =>	"major-revision-date",
		"MS" =>	"mesh-scope-note",
		"N1" => "cas-type-1-name",
		"OL" =>	"online-note",
		"PA" =>	"pharmacological-action",
		"PI" =>	"previous-indexing", 
		"PM" =>	"public-mesh-note",
		"PX" =>	"pre-explosion",
		"RECTYPE" =>	"record-type",
		"RH" =>	"running-head",
		"RN" => "registry-number",
		"RR" =>	"related-cas-registry-number",
		"ST" =>	"semantic-type",
		"UI" =>	"unique-identifier"
	);
	//see: http://www.nlm.nih.gov/mesh/dtype.html
	private static $descriptor_data_elements_subfields = array(
		"a" => "the term itself",
		"b" => "SEMANTIC TYPE",
		"c" => "LEXICAL TYPE",
		"d" => "SEMANTIC RELATION",
		"e" => "THESAURUS ID",
		"f" => "DATE",
		"s" => "SORT VERSION",
		"v" => "ENTRY VERSION",
	);
	//see: http://www.nlm.nih.gov/mesh/qtype.html
	private static $qualifier_data_elements = array(
		"AN" => "annotation",
		"DA" => "date-of-entry",
		"DQ" => "date-qualifier-established",
		"GM" => "grateful-med-note",
		"HN" => "histrory-note",
		"MED" => "backfile-posting",
		"M94" => "backfile-posting",
		"M90" => "backfile-posting",
		"M85" => "backfile-posting",
		"M80" => "backfile-posting",
		"M75" => "backfile-posting",
		"M66" => "backfile-posting",
		"MR" =>"major-revision-date",
		"MS" => "scope-note",
		"OL" => "online-note",
		"QA" => "topical-qualifier-abbreviation",
		"QA" =>	"topical-qualifier-abbreviation",
		"QE" =>	"qualifier-entry-version",
		"QS" =>	"qualifier-sort-version",
		"QT" =>	"qualifier-type",
		"QX" =>	"qualifier-cross-reference",
		"RECTYPE" => "record-type",
		"SH" =>	"subheading",
		"TN" =>	"tree-node-allowed",
		"UI" =>	"unique-identifier",
		"MED" => "backfile-posting"
	);
	//see https://www.nlm.nih.gov/mesh/xmlconvert.html
	private static $supplementary_concept_records = array(
		"DA" => "date-of-entry",
		"FR" =>	"frequency",
		"HM" => "heading-mapped-to",
		"II" =>	"indexing-information",
		"MR" => "major-revision-date",
		"N1" =>	"cas-type-1-name",
		"NM" =>	"name-of-substance",
		"NM_TH" => "nm-term-thesaurus-id",
		"NO" =>	"note",
		"PA" =>	"pharmacological-action",
		"PI" => "previous-indexing",
		"RECTYPE" => "record-type",
		"RN" => "cas-registry-number-or-ec-number",
		"RR" =>	"related-cas-registry-number",
		"SO" => "source",
		"ST" => "semantic-type",
		"SY" => "synonym",
		"TH" => "thesaurus-id",
		"UI" => "unique-identifier"
	);
	private  $bio2rdf_base = "http://bio2rdf.org/";
	private  $mesh_vocab ="mesh_vocabulary:";
	private  $mesh_resource = "mesh_resource:";
	private $version = 0.3;
	function __construct($argv) {
		parent::__construct($argv, "mesh");
		parent::addParameter('files', true, 'all|descriptors|qualifiers|supplementary', 'all', 'all or comma-separated list of files to process');
		parent::addParameter('download_url',false,'','ftp://nlmpubs.nlm.nih.gov/online/mesh/YEAR/asciimesh/','default ftp location');
		parent::addParameter('year', false, '','2019',"Year to process");
		parent::initialize();
	  }//constructor

	function Run(){
		$sp = trim(parent::getParameterValue('files'));
	  	if($sp == 'all'){
	  		$files = $this->getPackageMap();
	  	}else{
	  		$s_a = explode(",", $sp);
	  		$pm = $this->getPackageMap();
	  		$files = array();
	  		foreach($s_a as $a){
	  			if(array_key_exists($a, $pm)){
	  				$files[$a] = $pm[$a];
	  			}
	  		}
	  	}//else

	  	$ldir = parent::getParameterValue('indir');
		$odir = parent::getParameterValue('outdir');
		$dd = '';

	  	//now iterate over the files array
		$year = parent::getParameterValue('year');
		foreach ($files as $k => $fpattern){
			$file = str_replace("YEAR",$year,$fpattern);
			$lfile = $ldir.$file;
			$rfile = parent::getParameterValue("download_url").$file;
			$rfile = str_replace("YEAR",$year,$rfile);
			// download if necessary
			if(!file_exists($lfile) || parent::getParameterValue('download') == "true") {
				echo "Downloading $file ... ";
				$ret = utils::downloadSingle($rfile,$lfile);
				if($ret === FALSE) {
					trigger_error("Unable to get $file", E_USER_ERROR);
					continue;
				}
				echo "done!".PHP_EOL;
			}

			//set the outfile
			$ofile = "bio2rdf-mesh-".$k.".".parent::getParameterValue('output_format'); 
			$gz= strstr(parent::getParameterValue('output_format'), "gz")?true:false;

			echo "processing $k ...";
			parent::setReadFile($lfile, FALSE);
			parent::setWriteFile($odir.$ofile, $gz);
			$fnx = $k;
			$this->$fnx();
			parent::writeRDFBufferToWriteFile();
			parent::getWriteFile()->close();
			echo "done!".PHP_EOL;

			$source_file = (new DataResource($this))
                         ->setURI($rfile)
                         ->setTitle("MeSH")
                         ->setRetrievedDate(parent::getDate(filemtime($lfile)))
                         ->setFormat("text/x-mesh-record")
                         ->setPublisher("http://www.nlm.nih.gov")
                         ->setHomepage("http://www.nlm.nih.gov/mesh/")
                         ->setRights("use")
                         ->setLicense("http://www.nlm.nih.gov/databases/download.html")
                         ->setDataset("http://identifiers.org/mesh/");

			$prefix = parent::getPrefix();
			$bVersion = parent::getParameterValue('bio2rdf_release');
			$date = parent::getDate(filemtime($odir.$ofile));

			$output_file = (new DataResource($this))
                         ->setURI("http://download.bio2rdf.org/release/$bVersion/$prefix/$ofile")
                         ->setTitle("Bio2RDF v$bVersion RDF version of $prefix")
                         ->setSource($source_file->getURI())
                         ->setCreator("https://github.com/bio2rdf/bio2rdf-scripts/blob/master/mesh/mesh.php")
                         ->setCreateDate($date)
                         ->setHomepage("http://download.bio2rdf.org/release/$bVersion/$prefix/$prefix.html")
                         ->setPublisher("http://bio2rdf.org")
                         ->setRights("use-share-modify")
                         ->setRights("by-attribution")
                         ->setRights("restricted-by-source-license")
                         ->setLicense("http://creativecommons.org/licenses/by/3.0/")
                         ->setDataset(parent::getDatasetURI());

			if($gz) $output_file->setFormat("application/gzip");
			if(strstr(parent::getParameterValue('output_format'),"nt")) $output_file->setFormat("application/n-triples");
			else $output_file->setFormat("application/n-quads");

			$dd .= $source_file->toRDF().$output_file->toRDF();
		}//foreach

		parent::setWriteFile($odir.$this->getBio2RDFReleaseFile($this->getNamespace()));
		parent::getWriteFile()->write($dd);
		parent::getWriteFile()->close();
		echo "done!".PHP_EOL;
	}//run

	private function supplementary(){
		$sup_rec = "";
		while(FALSE !== ($aLine = $this->GetReadFile()->Read(200000))){
			if(strlen($aLine) == 0){
				$dR = $this->readRecord($sup_rec);
				$this->makeSupplementaryRecord($dR);
				$sup_rec = "";
				continue;
			}
			preg_match("/\*NEWRECORD/", $aLine, $matches);
			if(count($matches) == 0){
				$sup_rec .= $aLine.PHP_EOL;
			}			
		}
	}
	private function descriptors(){
		$descriptor_record = "";
		while(FALSE !== ($aLine = $this->GetReadFile()->Read(200000))){
			if(strlen($aLine) == 0){
				$dR = $this->readRecord($descriptor_record);
				$this->makeDescriptorRecord($dR);
				$descriptor_record = "";
				continue;
			}
			preg_match("/\*NEWRECORD/", $aLine, $matches);
			if(count($matches) == 0){
				$descriptor_record .= $aLine.PHP_EOL;
			}			
		}
	}

	private function qualifiers(){
		$qualifier_record = "";
		while(FALSE !== ($aLine = $this->GetReadFile()->Read(200000))){
			if(strlen($aLine) == 0){
				$qR = $this->readRecord($qualifier_record);
				$this->makeQualifierRecordRDF($qR);
				$qualifier_record = "";
				continue;
			}
			preg_match("/\*NEWRECORD/", $aLine, $matches);
			if(count($matches) == 0){
				$qualifier_record .= $aLine.PHP_EOL;
			}			
		}
	}
	/**
	* add an RDF representation of the incoming param to the model.
	* @$desc_record_arr is an assoc array with the contents of one qualifier record
	*/
	private function makeSupplementaryRecord($sup_record_arr){
		//get the UI of the supplementary record

		if(!isset($sup_record_arr['UI'][0]) or !isset($sup_record_arr['NM'][0])) return;

		$sr_ui = $sup_record_arr["UI"][0];
		$sr_res = $this->getNamespace().$sr_ui;
		$sr_label = $sup_record_arr['NM'][0];

		parent::addRDF(
			parent::describeIndividual($sr_res, $sr_label, $this->getVoc()."Supplementary-Descriptor",$sr_label).
			parent::describeClass($this->getVoc()."Supplementary-Descriptor", "MeSH Supplementary Descriptor" )
		);
		//now get the descriptor_data_elements
		$sde = $this->getSupplementaryConceptRecords();
		//iterate over the properties
		foreach($sup_record_arr as $k => $v){
			if(array_key_exists($k, $sde)){
				//add date of entry
				if($k == "DA"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['DA'], $this->formatDate($vv), "xsd:date").
							parent::describeProperty($this->getVoc().$sde['DA'], "Relationship between a supplementary record and its date of entry")
						);
					}
				}//if
				if($k == "FR"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['FR'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['FR'], "Relationship between a supplementary record and its frequency")
						);
					}
				}//if
				if($k == "HM"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['HM'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['HM'], "Relationship between a supplementary record and its heading mapping")
						);
					}
				}//if
				if($k == "II"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['II'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['II'], "Relationship between a supplementary record and its indexing information")
						);
					}
				}//if
				if($k == "MR"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['MR'], $this->formatDate($vv), "xsd:date").
							parent::describeProperty($this->getVoc().$sde['MR'], "Relationship between a supplementary record and its major revision date")
						);
					}
				}//if
				if($k == "N1"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['N1'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['N1'], "Relationship between a supplementary record and its cas 1 name")
						);
					}
				}//if
				if($k == "NM"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['NM'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['NM'], "Relationship between a supplementary record and its name of substance")
						);
					}
				}//if
				if($k == "NM_TH"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['NM_TH'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['NM_TH'], "Relationship between a supplementary record and its term thesaurus id")
						);
					}
				}//if
				if($k == "NO"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['NO'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['NO'], "Relationship between a supplementary record and its note")
						);
					}
				}//if
				if($k == "PA"){
					foreach($v as $kv => $vv){
						$vlabel = utf8_encode(htmlspecialchars($vv));
						$vid = parent::getRes().md5($vv);
						parent::AddRDF(
							parent::describeIndividual($vid, $vlabel, parent::getVoc()."Pharmacological-Action",$vlabel).
							parent::triplify($sr_res, $this->getVoc().$sde['PA'],$vid).
							parent::describeProperty($this->getVoc().$sde['PA'], "Relationship between a supplementary record and its pharmacological action")
						);
					}
				}//if
				if($k == "PI"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['PI'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['PI'], "Relationship between a supplementary record and its previous indexing")
						);
					}
				}//if
				if($k == "RECTYPE"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['RECTYPE'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['RECTYPE'], "Relationship between a supplementary record and its record type")
						);
					}
				}//if
				if($k == "RN"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['RN'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['RN'], "Relationship between a supplementary record and its cas registry number or ec number")
						);
					}
				}//if
				if($k == "RR"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['RR'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['RR'], "Relationship between a supplementary record and its related cas registry number")
						);
					}
				}//if
				if($k == "SO"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['SO'], addslashes(utf8_encode(htmlspecialchars($vv)))).
							parent::describeProperty($this->getVoc().$sde['SO'], "Relationship between a supplementary record and its source")
						);
					}
				}//if
				if($k == "ST"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['ST'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['ST'], "Relationship between a supplementary record and its semantic type")
						);
					}
				}//if
				if($k == "SY"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['SY'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['SY'], "Relationship between a supplementary record and its synonym")
						);
					}
				}//if
				if($k == "TH"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($sr_res, $this->getVoc().$sde['TH'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$sde['TH'], "Relationship between a supplementary record and its thesaurus id")
						);
					}
				}//if

			}else{
				trigger_error("Please add key to descriptor record map: ".$k.PHP_EOL, E_USER_ERROR);
			}
			$this->WriteRDFBufferToWriteFile();
		}//foreach
		$this->WriteRDFBufferToWriteFile();
	}
	/**
	* add an RDF representation of the incoming param to the model.
	* @$desc_record_arr is an assoc array with the contents of one qualifier record
	*/
	private function makeDescriptorRecord($desc_record_arr){
		//get the UI of the descriptor record
		$dr_ui = $desc_record_arr["UI"][0];
		$dr_res = $this->getNamespace().$dr_ui;
		$dr_label = $desc_record_arr['MH'][0];

		parent::AddRDF(
			parent::describeIndividual($dr_res, $dr_label, $this->getVoc()."Descriptor",$dr_label).
			parent::describeClass($this->getVoc()."Descriptor", "MeSH Descriptor" )
		);
		//now get the descriptor_data_elements
		$qde = $this->getDescriptorDataElements();
		//iterate over the properties
		foreach($desc_record_arr as $k => $v){
			if(array_key_exists($k, $qde)){
				if($k == "AN"){
					foreach($v as $kv => $vv){
						//explode by semicolon
						$vvrar = explode(";", $vv);
						foreach($vvrar as $anAn){
							parent::AddRDF(
								parent::triplifyString($dr_res, $this->getVoc().$qde["AN"], addslashes($anAn)).
								parent::describeProperty($this->getVoc().$qde["AN"], "Relationship between a descriptor and its annotation")
							);
						}//foreach
					}//foreach
				}//if
				//add allowable topical qualifiers
				if($k == "AQ"){
					//$x = $this->getDescriptorDataElements();
					foreach($v as $kv => $vv){
						$vvrar = explode(" ", $vv);
						foreach($vvrar as $aq){
							$aq_res = $this->getRes().$aq;
							parent::AddRDF(
								parent::triplify($aq_res, "rdf:type", $this->getVoc()."allowable-topical-qualifier").
								parent::describeClass($this->getVoc()."allowable-topical-qualifier", "allowable topical qualifier: ".$qde['AQ'])
							);
							parent::AddRDF(
								parent::triplify($dr_res, $this->getVoc().$qde['AQ'], $aq_res).
								parent::describeProperty($this->getVoc().$qde['AQ'], "Relationship between a descriptor and its allowable topical qualifiers")
							);
						}//foreach
					}//foreach
				}//if
				//add CATALOGING SUBHEADINGS LIST NAME
				if($k == "CATSH"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['CATSH'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['CATSH'], "Relationship between a descriptor and its cataloging subheadings list name" )
						);
					}			
				}//if
				if($k == "CX"){
					foreach($v as $kv=> $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['CX'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['CATSH'], "Relationship between a descriptor and xrefs")
						);
					}	
				}//if
				//add date of entry
				if($k == "DA"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['DA'], $this->formatDate($vv), "xsd:date").
							parent::describeProperty($this->getVoc().$qde['DA'], "Relationship between a descriptor and its date of entry")
						);
					}
				}//if
				//descriptor class
				if($k == "DC"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['DC'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['DC'], "Relationship between a descriptor and its descriptor class")
						);
					}
				}//if
				//descriptor entry version
				if($k == "DE"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['DE'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['DE'], "Relationship between a descriptor record and its entry version")
						);
					}
				}//if
				//descriptor sort version
				if($k == "DS"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['DS'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['DS'], "Relationship between a descriptor record and its sort version")
						);
					}
				}//if
				//date major descriptor established 
				if($k == "DX"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['DX'], $this->formatDate($vv) , "xsd:date").
							parent::describeProperty($this->getVoc().$qde['DX'], "Relationship between a descriptor and its date of major descriptor established")
						);
					}
				}//if
				if($k == "EC"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['EC'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['EC'], "Relationship between a descriptor and its entry combination")
						);
					}
				}
				if($k == "PRINT ENTRY"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['PRINT ENTRY'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['PRINT ENTRY'], "Relationship between a descriptor and its print entry term")
						);
					}
				}
				if($k == "ENTRY"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['ENTRY'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['ENTRY'], "Relationship between a descriptor and its entry term")
						);
					}
				}
				if($k == "FX"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['FX'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['FX'], "Relationship between a descriptor and its forward cross reference")
						);
					}
				}
				if($k == "GM"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['GM'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['GM'], "Relationship between a descriptor and its grateful med note")
						);
					}
				}
				if($k == "HN"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['HN'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['HN'], "Relationship between a descriptor record and its history note")
						);
					}
				}
				if($k == "MED"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['MED'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['MED'], "Relationship between a descriptor and its backfile postings")
						);
					}
				}
				if($k == "M94"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['M94'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M94'], "Relationship between a descriptor and its backfile postings")
						);
					}
				}
				if($k == "M90"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['M90'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M90'], "Relationship between a descriptor and its backfile postings")
						);
					}
				}
				if($k == "M85"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['M85'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M85'], "Relationship between a descriptor record and its backfile postings")
						);
					}
				}
				if($k == "M80"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['M80'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M80'], "Relationship between a descriptor record and its backfile postings")
						);
					}
				}
				if($k == "M75"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['M75'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M75'], "Relationship between a descriptor record and its backfile postings")
						);
					}
				}
				if($k == "M66"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['M66'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M66'], "Relationship between a descriptor record and its backfile postings")
						);
					}
				}
				
				if($k == "MH_TH"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['MH_TH'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['MH_TH'], "Relationship between a descriptor record and its MeSH Heading thesaurus id")
						);
					}
				}
				if($k == "MH"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['MH'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['MH'], "Relationship between a descriptor record and its MeSH Heading")
						);
					}
				}
				if($k == "MN"){
					foreach($v as $kv => $vv){
						$vid = parent::getNamespace().$vv;
						$vlabel = utf8_encode(htmlspecialchars($vv));
						parent::AddRDF(
							parent::describeIndividual($vid,$dr_label,parent::getVoc()."Tree-Entry",$vlabel).
							parent::triplify($dr_res, $this->getVoc().$qde['MN'], $vid).
							parent::describeProperty($this->getVoc().$qde['MN'], "Relationship between a descriptor record and its MeSH Tree Number")
						);
						if(FALSE !== ($pos = strrpos($vv,"."))) {
							$pid = parent::getNamespace().substr($vv,0,$pos);
							parent::addRDF(parent::triplify($vid,"rdfs:subClassOf",$pid));
						}

					}
				}
				if($k == "MR"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['MR'], $this->formatDate($vv) , "xsd:date").
							parent::describeProperty($this->getVoc().$qde['MR'], "Relationship between a descriptor record and its major revision date")
						);
					}
				}
				
				if($k == "MS"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['MS'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['MS'], "Relationship between a descriptor record and its MeSH scope note")
						);
					}
				}
				if($k == "N1"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['N1'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['N1'], "Relationship between a descriptor record and its CAS 1 name")
						);
					}
				}
				if($k == "OL"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['OL'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['OL'], "Relationship between a descriptor record and its online note")
						);
					}
				}
				if($k == "PA"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['PA'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['PA'], "Relationship between a descriptor record and its pharmacological action")
						);
					}
				}
				if($k == "PI"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['PI'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['PI'], "Relationship between a descriptor record and its previous indexing")
						);
					}
				}
				if($k == "PM"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['PM'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['PM'], "Relationship between a descriptor record and its public mesh note")
						);
					}
				}
				if($k == "PX"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['PX'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['PX'], "Relationship between a descriptor record and its pre explosion")
						);
					}
				}
				if($k == "RECTYPE"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['RECTYPE'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['RECTYPE'], "Relationship between a descriptor record and its record type")
						);
					}
				}
				if($k == "RH"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['RH'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['RH'], "Relationship between a descriptor record and its running head, in relation to mesh tree structures")
						);
					}
				}
				if($k == "RN"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['RN'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['RN'], "Relationship between a descriptor record and its CAS registry")
						);
					}
				}
				if($k == "RR"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($dr_res, $this->getVoc().$qde['RR'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['RR'], "Relationship between a descriptor record and its registry number")
						);
					}
				}
				if($k == "ST"){
					foreach($v as $kv => $vv){
                                                $vid = parent::getNamespace().$vv;
						$pid = parent::getNamespace().substr($vv,0,strrpos($vv,".")-1);
                                                $vlabel = utf8_encode(htmlspecialchars($vv));
  						parent::AddRDF(
							parent::describeIndividual($vid,$vlabel,parent::getVoc()."Semantic-Type",$vlabel).
							parent::triplify($dr_res, $this->getVoc().$qde['ST'], $vid).
							parent::describeProperty($this->getVoc().$qde['ST'], "Relationship between a descriptor record and its semantic type")
						);
					}
				}



			}else{
				trigger_error("Please add key to descriptor record map: ".$k.PHP_EOL, E_USER_ERROR);
			}
			$this->WriteRDFBufferToWriteFile();
		}//foreach
		$this->WriteRDFBufferToWriteFile();
	}
	/**
	* add an RDF representation of the incoming param to the model.
	* @$qual_record_arr is an assoc array with the contents of one qualifier record
	*/
	private function makeQualifierRecordRDF($qual_record_arr){
		//get the UI of the qualifier record
		$qr_ui = $qual_record_arr["UI"][0];
		$qr_res = $this->getNamespace().$qr_ui;
		$qr_label = $qual_record_arr['SH'][0];

		parent::AddRDF(
			parent::describeIndividual($qr_res, $qr_label, $this->getVoc()."Qualifier-Descriptor",$qr_label).
			parent::describeClass($this->getVoc()."Qualifier-Descriptor", "MeSH Qualifier Descriptor")
		);
		//now get the descriptor_data_elements
		$qde = $this->getQualifierDataElements();
		//iterate over the properties
		foreach($qual_record_arr as $k => $v){
			if(array_key_exists($k, $qde)){
				if($k == "AN"){
					foreach($v as $kv => $vv){
						//explode by semicolon
						$vvrar = explode(";", $vv);
						foreach($vvrar as $anAn){
							parent::AddRDF(
								parent::triplifyString($qr_res, $this->getVoc().$qde["AN"], addslashes($anAn)).
								parent::describeProperty($this->getVoc().$qde["AN"], "Relationship between a qualifier record and its annotation")
							);
						}//foreach
					}//foreach
				}//if
				if($k == "DA"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['DA'], $this->formatDate($vv) , "xsd:date").
							parent::describeProperty($this->getVoc().$qde['DA'], "Relationship between a qualifier record and its date of entry")
						);
					}
				}//if
				if($k == "DQ"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['DQ'], $this->formatDate($vv) , "xsd:date").
							parent::describeProperty($this->getVoc().$qde['DQ'], "Relationship between a qualifier record and its date qualifier established")
						);
					}
				}//if
				if($k == "GM"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['GM'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['GM'], "Relationship between a qualifier record and its grateful med note")
						);
					}
				}
				if($k == "HN"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['HN'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['HN'], "Relationship between a qualifier record and its history note")
						);
					}
				}
				if($k == "HN"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['HN'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['HN'], "Relationship between a qualifier record and its history note")
						);
					}
				}
				if($k == "MED"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['MED'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['MED'], "Relationship between a qualifier record and its backfile postings")
						);
					}
				}
				if($k == "M94"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['M94'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M94'], "Relationship between a qualifier record and its backfile postings")
						);
					}
				}
				if($k == "M90"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['M90'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M90'], "Relationship between a qualifier record and its backfile postings")
						);
					}
				}
				if($k == "M85"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['M85'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M85'], "Relationship between a qualifier record and its backfile postings")
						);
					}
				}
				if($k == "M80"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['M80'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M80'], "Relationship between a qualifier record and its backfile postings")
						);
					}
				}
				if($k == "M75"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['M75'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M75'], "Relationship between a qualifier record and its backfile postings")
						);
					}
				}
				if($k == "M66"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['M66'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['M66'], "Relationship between a qualifier record and its backfile postings")
						);
					}
				}
				if($k == "MR"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['MR'], $this->formatDate($vv) , "xsd:date").
							parent::describeProperty($this->getVoc().$qde['MR'], "Relationship between a qualifier record and its major revision date")
						);
					}
				}//if
				if($k == "MS"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['MS'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['MS'], "Relationship between a qualifier record and its MeSH scope note")
						);
					}
				}
				if($k == "OL"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['OL'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['OL'], "Relationship between a qualifier record and its online note")
						);
					}
				}
				if($k == "QA"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['QA'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['QA'], "Relationship between a qualifier record and its toplical qualifier abbreviation")
						);
					}
				}
				if($k == "QE"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['QE'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['QE'], "Relationship between a qualifier record and its qualifier entry version")
						);
					}
				}
				if($k == "QS"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['QS'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['QS'], "Relationship between a qualifier record and its qualifier sort version")
						);
					}
				}
				if($k == "QT"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['QT'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['QT'], "Relationship between a qualifier record and its qualifier type")
						);
					}
				}
				if($k == "QX"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['QX'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['QX'], "Relationship between a qualifier record and its qualifier cross reference")
						);
					}
				}
				if($k == "RECTYPE"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['RECTYPE'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['RECTYPE'], "Relationship between a qualifier record and its record type")
						);
					}
				}
				if($k == "SH"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['SH'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['SH'], "Relationship between a qualifier record and its subheading")
						);
					}
				}
				if($k == "TN"){
					foreach($v as $kv => $vv){
						parent::AddRDF(
							parent::triplifyString($qr_res, $this->getVoc().$qde['TN'], utf8_encode(htmlspecialchars($vv))).
							parent::describeProperty($this->getVoc().$qde['TN'], "Relationship between a qualifier record and its tree node allowed")
						);
					}
				}
				
			}else{
				trigger_error("Please add key to qualifier record map: ".$k.PHP_EOL, E_USER_ERROR);
			}//else
			$this->WriteRDFBufferToWriteFile();
		}//foreach
		$this->WriteRDFBufferToWriteFile();
	}//makeQualifierRecord


	/**
	* Return an assoc array with the contents of the qualifier record
	*/
	private function readRecord($aRecord){
		$returnMe = array();
		$recArr = explode("\n", $aRecord);
		foreach($recArr as $ar){
			$al = explode(" = ", $ar);
			if(count($al) == 2){
				if(!array_key_exists($al[0], $returnMe)){
					$returnMe[$al[0]] = array($al[1]);
				}else{
					$returnMe[$al[0]][] = $al[1];
				}
			}
			
		}
		return $returnMe;
	}


	public function getPackageMap(){
		return self::$packageMap;
	}

	public function getSupplementaryConceptRecords(){
		return self::$supplementary_concept_records;
	}
	public function getQualifierDataElements(){
		return self::$qualifier_data_elements;
	}

	public function getDescriptorDataElements(){
		return self::$descriptor_data_elements;
	}

	public function formatDate($date)
	{
		$d = date_parse($date);
		return $d['year']."-".str_pad($d['month'],2,"0",STR_PAD_LEFT)."-".str_pad($d['day'],2,"0",STR_PAD_LEFT);
	}
}

?>
