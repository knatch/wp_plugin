<?php
/*
Plugin Name:Faceted Search for Existing RDBMS
Plugin URI: localhost/phptest/test1.php
Description: Excellent search system for your database
Author: Natch Khongpasuk and Nattapan Intamao
Author URI: www.facebook.com/nat.khongpasuk
Version: 0.1

*/




add_action('admin_menu', 'FacetDB_Submenu');



add_filter('upload_mimes', 'FacetDB_uploadType');     //Add upload file type: .SQL
add_filter( 'page_template', 'FacetDB_changePageTemplate');	// Call when displaying page





function FacetDB_changePageTemplate( $page_template )
{
	global $wpdb;
	$pageIDs = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_title like 'FacetDB%'");
	foreach($pageIDs as $pageID){
		if ( is_page($pageID)) {
			$page_template = dirname( __FILE__ ) . '/FacetDB_template.php';
			ob_start();
			get_header();
			get_sidebar();
			if (have_posts()) 
			{
				while ( have_posts()) 
				{
					the_post();
					the_title();
					the_content();	
				}
			}
			get_footer();
			$htmlStr = ob_get_contents();
			ob_end_clean(); 
			file_put_contents($page_template, $htmlStr);
		}
	}
	return $page_template;
}




function FacetDB_Submenu() 
{
	add_submenu_page( 'options-general.php', 'Facet DB Configuration', 'Facet DB Configuration', 'manage_options', 'facet-db-configuration-page', 'FacetDB_configurationPage' );
}

function FacetDB_uploadType($existing_mimes=array()) //Add upload file type
{
	$existing_mimes['sql'] = 'documents/midi';
	return $existing_mimes;
}


function FacetDB_handleUpload() //--------------------------------------------------------1
{
	if(isset($_FILES['uploadSql']))
	{
        $sqlFile = $_FILES['uploadSql'];
        $uploaded=media_handle_upload('uploadSql', 0);
        if(is_wp_error($uploaded))
		{
			echo "Error uploading file: " . $uploaded->get_error_message();
        }
		else
		{
			//Upload successfully
        }
		FacetDB_testQuery($uploaded);
    }
}

function FacetDB_testQuery($uploaded) //---------------------------------------------------2
{
	///// READ SQL FILE //////
	global $wpdb;
	$sql = file_get_contents(wp_get_attachment_url($uploaded));
	$templine = '';
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $sql) as $line)
	{	
		if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*')
			continue;
			$templine .= $line;
			
		if (substr(trim($line), -1, 1) == ';')
		{
			$wpdb->query($templine);
			$templine = '';
		}
	}
	wp_delete_attachment($uploaded);
}



function FacetDB_generatePost()// Success
{
	global $wpdb;
	if(isset($_POST['generateCode']))
	{
		$tableName=$_POST['generateCodeMain'];
		$tableNameFacts=$_POST['generateCodeFacts'];
		$talbeNameFacets=$_POST['generateCodeFacets'];
		$facetDBPostName = str_replace("facetDB_", "", $tableName);
		$facetDBPostName = str_replace("_Main", "", $facetDBPostName);
		$pageName='FacetDB Page-'.$facetDBPostName;
		$facetDBPost= array(
			'post_title'    => $pageName,
			'post_status'   => 'draft',
			'post_type'		=> 'page',
			);
		wp_insert_post($facetDBPost);
	}
	
}

function FacetDB_deleteTable()
{
	if(isset($_POST['tableDeleteButton']))
	{
		global $wpdb;
		$tableDeleteName = $_POST['tableDeleteName'];
		$wpdb->query("DROP TABLE $tableDeleteName");
		FacetDB_alert("Deleted Complete");
	}
}

function FacetDB_alert($msg) 
{
   // echo '<script type="text/javascript">alert("' . $msg . '"); </script>';
}


function FacetDB_configurationPage() 
{	
	echo '<div class="wrap">';
		echo '<h1>Facet DB Configuration Page</h1>';
	echo '</div>';
	

	global $wpdb;
	$wpDatabaseName=DB_NAME;
	$wpDatabaseName=strtolower($wpDatabaseName);
	$showWpDatabaseName=Tables_in_.$wpDatabaseName;
	
	
	FacetDB_deleteTable();
	FacetDB_generatePost();
	
	
	if(isset($_POST['textForm'])&&(isset($_POST['textBox']))) //Create Button: Upload file -------------> Segunda
	{	
		FacetDB_handleUpload();
		$temp= $_FILES["uploadSql"]["name"];
		$dbname= trim($temp , ".sql");
		echo 'Uploaded Table name: ';
		echo '<input type="text" name="textBox1" value="'.$temp.'" readonly><br><br>';
		echo '<h3>Table(s) imported successfully</h3>';
		echo '<form method="POST" action="#">';
			echo '<select id="tableBtn" name="tableName">';
			$myTables = $wpdb->get_results("SHOW TABLES");
			foreach($myTables as $myTable)
			{
				$table = $myTable->$showWpDatabaseName;
				echo '<option>'.$table.'</option>';
			}
			echo '</select>';
			echo '&nbsp&nbsp';
			echo '<input type="Submit" name="tableList" value="Choose">';
			echo '<input type="hidden" name="textBox2" value="'.$dbname.'">';
			echo '<input type="hidden" name="uploadFileName" value="'.$temp.'">';
		echo '</form>';
	}
	elseif(isset($_POST['tableListNotImport'])) // USE EXISTING TABLE ---------------------------> Segunda
	{
		$dbname = "";
		$tableName = $_POST['tableName'];
		echo '<h3>Use Existing Table</h3>';
		echo '<h3>Table Choosen:</h3>';	
		echo '<select id="tableBtn" name="tableName" DISABLED>';
			echo '<option>'.$tableName.'</option>';
		echo '</select>';
		echo '<br>';
		echo '<h4>Now, please select the key attribute of the table</h4>';
		echo '<form action="#" method="POST">';
		echo '<select id="IDBtnExisting" name="tableIDExisted">';
		foreach ( $wpdb->get_col( "DESC " . $tableName, 0 ) as $columnName )
		{
			echo '<option>'.$columnName.'</option>';
		}
		echo '</select>';
		echo '<h4>Now, please check some attributes you want to build faceted view</h4>';
		echo '<table>';
		foreach ( $wpdb->get_col( "DESC " . $tableName, 0 ) as $columnName ) 
		{
			echo  '<tr><td><input type="checkbox" name="check_list[]" value="'.$columnName.'"><label>';
			echo $columnName.'</label></td></tr>';
		}
		echo '</table>';
		echo '<input type="hidden" name="dbName" value="'.$dbname.'">';
		echo '<input type="hidden" name="tableName2" value="'.$tableName.'">';
		echo '<input type="Submit" name="tableList2" value="Choose">';
		echo '</form>';
	}
	elseif(isset($_POST['tableName'])&&(isset($_POST['textBox2']))) //IMPORTED TABLE ---------------> Trecera
	{
		$dbname = $_POST['textBox2'];
		$temp = $_POST['uploadFileName'];
		$tableName = $_POST['tableName'];
		echo 'Uploaded Table name: ';
		echo '<input type="text" name="textBox1" value="'.$temp.'" readonly><br><br><h3>Seleceted Table name<br></h3>';
		echo '<select id="tableBtn" name="tableName" DISABLED>';
			echo '<option>'.$tableName.'</option>';
		echo '</select>';
		
		echo '<br>';
		echo '<h4>Now, please select the key attribute of the table</h4>'; // Select key attribute
		echo '<form action="#" method="POST">';
		echo '<select id="IDBtn" name="tableID">';
		foreach ( $wpdb->get_col( "DESC " . $tableName, 0 ) as $columnName )  
			{
				echo '<option>'.$columnName.'</option>';
			}
		echo '</select>';
		echo '<h4>Almost there, please check some attributes you want to build faceted view</h4>';
		echo '<table>';
		foreach ( $wpdb->get_col( "DESC " . $tableName, 0 ) as $columnName ) //Select Facet attribute(s)
		{
			echo  '<tr><td><input type="checkbox" name="check_list[]" value="'.$columnName.'"><label>';
			echo $columnName.'</label></td></tr>';
		}
		echo '</table>';
		echo '<input type="hidden" name="dbName" value="'.$dbname.'">';
		echo '<input type="hidden" name="tableName2" value="'.$tableName.'">';
		echo '<input type="Submit" name="tableList2" value="Choose">';
		echo '<input type="hidden" name="uploadFileName2" value="'.$temp.'">';
		echo '</form>';
	}
	elseif(isset($_POST['tableName2'])&&(isset($_POST['dbName']))&&(isset($_POST['tableList2']))) // TABLE ------> Ultima
	{
		$dbname = $_POST['dbName'];
		$temp = $_POST['uploadFileName2'];
		$tableName = $_POST['tableName2'];
		if(isset($_POST['tableIDExisted']))
		{
			$tableKey=$_POST['tableIDExisted'];
		}
		else
		{
			$tableKey = $_POST['tableID'];
		}
		if($dbname!="")
		{
			echo 'Uploaded Table name: ';
			echo '<input type="text" name="textBox1" value="'.$temp.'" readonly><br><br><h3>Seleceted Table name<br></h3>';
			echo '<select id="tableBtn" name="tableName" readonly>';
				echo '<option>'.$tableName.'</option>';
			echo '</select>';
		}
		else
		{
			echo '<h3>Use Existing Table</h3>';
			echo '<h3>Table Choosen:</h3>';	
			echo '<select id="tableBtn" name="tableName" DISABLED>';
				echo '<option>'.$tableName.'</option>';
			echo '</select>';
			echo '<br>';
		}	
			echo '<h3>Key Attribute:</h3>';
			echo '<select id="tableIDOption" name="tableIDName" readonly>';
				echo '<option>'.$tableKey.'</option>';
			echo '</select>';
			echo '<h3>Selected Attributes:</h3>';
			echo '<table><tr><h4>';
			foreach($_POST['check_list'] as $check) 
			{
				echo '<td>'.$check.'</td>';
			}
			echo '</h4></tr></table>';
			
			
			////////Create Facets Table //////////

			
			$charset_collate = $wpdb->get_charset_collate();
			$sql="CREATE TABLE IF NOT EXISTS facetDB_".$tableName."_facets (
				id int(11) NOT NULL AUTO_INCREMENT,
				name varchar(100) NOT NULL,
				UNIQUE KEY id (id)
			)$charset_collate;";
			$tableNameFacets="facetDB_".$tableName."_facets";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta($sql);
			echo '<table>';
			foreach($_POST['check_list'] as $check)
			{
				$wpdb->insert( 
					$tableNameFacets, 
					array( 
						'name' => $check
					) 
				);
			}
			echo '</table>';
			
			///////Create Facts Table////////
			
			
			$sql="CREATE TABLE IF NOT EXISTS facetDB_".$tableName."_facts (
				item_id int(11) NOT NULL,
				facet_id int(11) NOT NULL,
				facet_name varchar(100) NOT NULL,
				value varchar(100) NOT NULL
				)$charset_collate;";
			$tableNameFacts="facetDB_".$tableName."_facts";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta($sql);
			$sqlInsert= "";
			$sqlnew = "";
			$maxArray=0;
			foreach($_POST['check_list'] as $check)
			{
				$myrows2 = $wpdb->get_results( "SELECT DISTINCT id,name FROM ".$tableNameFacets." where name like '".$check."' ");
					foreach($myrows2 as $myrow) // For attribute name
					{
						$facet_id         = $myrow->id;
						$facet_facet      = $myrow->name;
						$sqlNew =  "insert into $tableNameFacts (item_id,facet_id,facet_name,value)
									select t1.$tableKey ,t2.id,t2.name,t1.$check
									from  $tableName t1 ,  $tableNameFacets t2
									where t2.name = '$check'";
									
						echo $sqlNew;
						$wpdb->query($sqlNew);
						/*$myrows = $wpdb->get_results( "SELECT DISTINCT ".$tableKey.", ".$check."  FROM ".$tableName." ");
						foreach($myrows as $myrow)
						{
							$fact_id         = $myrow->$tableKey;
							$fact_facet      = $myrow->$check;
							$wpdb->insert( 
								$tableNameFacts, 
								array( 
								'item_id' => $fact_id,
								'facet_id'=> $facet_id,
								'facet_name'=>$facet_facet,
								'value'=>$fact_facet
								) 
							);*/
							//$sqlInsert .= "INSERT INTO ".$tableNameFacts." VALUES (".$fact_id.",".$facet_id.",'".$facet_facet."','".$fact_facet."');";
							/*
							$sqlInsert[$maxArray]= "INSERT INTO ".$tableNameFacts." VALUES (".$fact_id.",".$facet_id.",'".$facet_facet."','".$fact_facet."');\n ";
							$maxArray=$maxArray+1;*/
						
					}
			}
			
			/*
			for($i=0;$i<$maxArray;$i++)
			{
				//echo $sqlInsert[$i].'<br>';
				$wpdb->query($sqlInsert[$i]);
			}
			/*
			/*$sqlInsertPage = dirname( __FILE__ ) . '\SQL Reader/facetDB_sqlReader.txt';
			ob_start();
			echo $sqlInsert;
			$htmlStr = ob_get_contents();
			ob_end_clean(); 
			file_put_contents($sqlInsertPage, $htmlStr);
			$sqlInsert = file_get_contents($sqlInsertPage);
			echo $sqlInsert;
			$templine = '';
			foreach(preg_split("/((\r?\n)|(\r\n?))/", $sqlInsert) as $line)
			{
				if (substr(trim($line), -1, 1) == ';')
				{
					$wpdb->query($templine);
					$templine = '';
				}
			}
			*/
			
			
			echo '<h3>Fact table and Facets table are created successfully</h3>';
			
			
			//Rename the main table
			
			$tableNameAlter="facetDB_".$tableName."_Main";
			$wpdb->query("RENAME TABLE ".$tableName." TO ".$tableNameAlter." ");
			$tableName=$tableNameAlter;
			
			
			// Generate Code
			
			
			echo '<form method="POST" action="#">
				<input type="submit" name="generateCode" value="Generate Code">
				<input type="hidden" name="generateCodeMain" value="'.$tableName.'">
				<input type="hidden" name="generateCodeFacts" value="'.$tableNameFacts.'">
				<input type="hidden" name="generateCodeFacets" value="'.$tableNameFacets.'">
			</form>';
			
			
			//Test 
			
			echo '<form method="POST" action="#">
				<input type="submit" name="createTagCode" value="To Test Page">
				<input type="hidden" name="createTagCode3" value="'.$tableName.'">
				<input type="hidden" name="createTagCode4" value="'.$tableNameFacts.'">
				<input type="hidden" name="createTagCode5" value="'.$tableNameFacets.'">
			</form>';
			
	}
	else // Start Page -------------------------------> primera
	{
		if((isset($_POST['createTagCode'])) || (isset($_POST['showTableNameButton'])))//Go to another function that create our 2 tables
		{
			if(isset($_POST['showTableName']))
			{
				$tableName = $_POST['showTableName'];
				$tableNameFacts = str_replace("main", "facts", $tableName);
				$tableNameFacets = str_replace("main", "facets", $tableName);
			}
			else
			{
				$tableName = $_POST['createTagCode3'] ;
				$tableNameFacts = $_POST['createTagCode4'] ;
				$tableNameFacets = $_POST['createTagCode5'] ;
			}
			$attributeList = $wpdb->get_col("SELECT name from ".$tableNameFacets." ");
			FacetDB_createPage($attributeList,$tableName,$tableNameFacts,$tableNameFacets);
		}
		else
		{
			echo '
			<div style="float:left;width:50%;">
				<div style="padding-right:10px;">';
				echo 'Choose an existing table or import a sql file from your pc<br><br>';
				echo '<h3>Use Existing Table</h3>';
				echo '<h4>Choose a table you want to build with faceted search:</h4>';
				echo '<form method="POST" action="#">';
				echo '<select id="tableBtn" name="tableName">';
				$myTables = $wpdb->get_results("SHOW TABLES");
				foreach($myTables as $myTable)
				{
					echo $myTables;
					$table = $myTable->$showWpDatabaseName;
					
					echo '<option>'.$table.'</option>';
				}
				echo '</select>';
				echo '&nbsp&nbsp';
				echo '<input type="Submit" name="tableListNotImport" value="Choose">';
				echo '</form>';
			echo '<br><br><h3>Delete Table</h3>';
			echo '<form action="#" method="POST">';
			echo '<select name="tableDeleteName">';
				$myTables = $wpdb->get_results("SHOW TABLES WHERE ".$showWpDatabaseName." NOT LIKE '".$wpDatabaseName."%'");
				foreach($myTables as $myTable)
				{
					echo $myTables;
					$table = $myTable->$showWpDatabaseName;
					
					echo '<option>'.$table.'</option>';
				}
				echo '</select>';
				echo '&nbsp&nbsp';
			echo '<input type="Submit" name="tableDeleteButton" value="Delete">';
			echo '</form>';
			
			echo '</div></div>
			<div style="float:right;width:50%;">
				<div style="padding-left:10px;">';
				
				
				echo '<br><br><h3>Import a new Table Table</h3>';
				echo '<h4>Import a sql file containing the table you want to build with faceted search:</h4>';
			echo
				"<form  action='#' method='POST' enctype=multipart/form-data>
					<input type='file' id='uploadSql' name='uploadSql'></input>
					<input type='hidden' name='textForm' value='textForm'></input>
					<input type='submit' name='textBox' value='Upload'></input>
				</form>";
				
				
				echo '<br><br><h3>Make it to Table(Choose Main)</h3>';
				echo '<form method="POST" action="#">';
				echo '<select id="tableShow" name="showTableName">';
					$myTables = $wpdb->get_results("SHOW TABLES");
					foreach($myTables as $myTable)
					{
						echo $myTables;
						$table = $myTable->$showWpDatabaseName;
						
						echo '<option>'.$table.'</option>';
					}
				echo '</select>';
				echo '&nbsp&nbsp';
				echo '<input type="Submit" name="showTableNameButton" value="Make to facet Page">';
				echo '</form>';
			echo	"</div>
			</div>
			<div style='clear: both;'></div>";
			
			
				
		}
	}
	
}


function FacetDB_createPage($attributeList,$tableName,$tableNameFacts,$tableNameFacets)
{
	global $wpdb;
	if((isset($_POST['createTagCode']))||(isset($_POST['showTableNameButton'])))
	{
		if(isset($_POST['submitFacet']))
		{
			$tableName = $_POST['createTagCode7'];
			$tableNameFacts = $_POST['createTagCode8'];
			$tableNameFacets = $_POST['createTagCode9'];
			$attributeList = $wpdb->get_col("SELECT name from ".$tableNameFacets." ");
			$numberOfcheckFacet=0;
			foreach($_POST['chooseList'] as $check) //Get the checked Value and its facet
			{
				$selectedRow = $wpdb->get_row('SELECT facet_name FROM '.$tableNameFacts.' WHERE value like "%'.$check.'%"');
				$checkFacet[$numberOfcheckFacet] = $selectedRow->facet_name;
				$numberOfcheckFacet=$numberOfcheckFacet+1;
			}
		}
		$createTagCode=$_POST['createTagCode'];
		$submitFacet=$_POST['submitFacet'];

		echo '<div style="float:left;width:30%;">
		<div style="padding-right:10px;">';
		

			echo '<form action="#" method="POST">';		
				echo '<table>';
				foreach($attributeList as $check) 
				{
					echo '<tr><td><b>'.$check.'</b></td></tr>';
					$myrows=$wpdb->get_results('SELECT value , count(*) AS c FROM '.$tableNameFacts.' WHERE facet_name like "'.$check.'" GROUP BY facet_id, value');
					foreach($myrows as $myrow)
					{
						$value= $myrow->value;
						$count= $myrow->c;
						echo "<tr><td><input type='checkbox' name='chooseList[]' value='".$value."'><label>";
						echo $value.'('.$count.')';
						echo '</td></tr>';
						echo "</label>";
					}
				}
				echo '</table>';
			echo "<input type='hidden' name='createTagCode' value='".$createTagCode."'>";
			echo '<input type="hidden" name="createTagCode7" value="'.$tableName.'">';
			echo '<input type="hidden" name="createTagCode8" value="'.$tableNameFacts.'">';
			echo '<input type="hidden" name="createTagCode9" value="'.$tableNameFacets.'">';
			echo "<input type='submit' name='submitFacet' value='Submit'/>";
			echo '</form>';
		
		
		//Right Side
		
		echo 
		'</div></div>
		<div style="float:right;width:70%;">
		<div style="padding-left:10px;">';

			echo '<table><tr>';
			$count=0;
			$listItem;
			foreach ( $wpdb->get_col( "DESC " . $tableName, 0 ) as $columnName ) 
			{
				echo  '<td>'.$columnName.'</td>';
				$listItem[$count]=$columnName;
				$count=$count+1;
			}
			echo '</tr>';
			$max=$count;
			if(isset($_POST['submitFacet']) && (isset($_POST['chooseList'])))
			{
				$setFacet=FALSE;
				$j=0;
				foreach($_POST['chooseList'] as $check)
				{
					$checked[$j]=$check;
					$letter[$j]= "t".$j;
					$j=$j+1;
					
				}
				$j=0;
				$i=0;
				$t=0;
				$sql= 'SELECT '.$letter[$t].'.* FROM ';
				$on = ' ON';
				do
				{
					$sql=$sql."(SELECT * FROM ".$tableName." WHERE ".$checkFacet[$j]." like '".$checked[$j]."' ";
					for($i=($j+1);$i<$numberOfcheckFacet;$i++)
					{
						if((isset($checkFacet[$i])) &&($checkFacet[$i]==$checkFacet[$j]))
						{
							$j=$j+1;
							$sql = $sql."OR ".$checkFacet[$j]." like '".$checked[$j]."' ";
						}
					}
					$sql=$sql.") ".$letter[$t]."";
					$t=$t+1;
					$j=$j+1;
					if(isset($checkFacet[$j]))
					{  
						$sql=$sql." INNER JOIN ";
						$endOfSql=$endOfSql." ".$letter[$t-1].".id = ".$letter[$t].".id AND";
					}
				}while($j<$numberOfcheckFacet);
				if(isset($endOfSql))
				{
					$sql= $sql.$on.trim($endOfSql,"AND");
				}
				else
				{
					$sql=$sql;
				}
				$sql = $wpdb->get_results($sql);
			}
			else
			{
				$sql = $wpdb->get_results('SELECT * FROM '.$tableName.';');
			}			
			foreach($sql as $item)
			{
				echo '<tr>';
				for($count=0;$count<$max;$count++)
				{
					$value= $item->$listItem[$count];
					echo '<td>'.$value.'</td>';
				}
				echo '</tr>';
			}
			echo '</table>';
		echo 
		'</div></div>
		<div style="clear: both;"></div>';
	}
}
?>