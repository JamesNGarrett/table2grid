<?php

/*
function d($t){
	echo "<pre>" .  print_r($t,1) ."</pre>";
}

function dd($t){
	d($t);
	die();
}
*/
class plgContentTable2grid extends JPlugin{

	private $content = "";
	private $new_content = "";
	private $table_map = [];
	private $offset = 0;
	private $t_depth = 0;
	private $browser_class = '';
	private $browser_version;
		
	public function onContentPrepare($context, &$article, &$params, $limitstart)
	{	
		//$start_time = microtime();
		if(empty($article->text) || strpos($article->text,"table2grid") === false){return;}
		
		$clientReflector = new ReflectionClass('JApplicationWebClient');
		$browsers_array = array_flip($clientReflector->getConstants());
		$browser_int = JFactory::getApplication()->client->browser;
		$this->browser_class = $browsers_array[$browser_int];
		$this->content = $article->text;
		
		do { 
			$return = $this->findEl(); 
		} while ($return); 
		
		$pos = 0;
		$end = strlen($this->content) - 1;
		foreach($this->table_map as $table_index => $t){

			$subtable_in_first_row = 0;
			$rowstr = "";	
			$substrings = [];
			$rowpos = $t['rowstart'];
			// count cells of the first row
			if( count($t['subtables'])){
				foreach($t['subtables'] as $st){
					if($st['start'] < $t['rowend']){
						$subtable_in_first_row = 1;
						//echo "st starts " . $st['start'] . " row end is " . $t['rowend'] . "<br>" ;
						$substrings[] = [$rowpos,$st['start']];
						$rowpos = st['end'];
					}
				}
				$substrings[] = [$rowpos,$t['rowend']];
				foreach($substrings as $ss){
					$rowstr .= substr($this->content, $ss[0],$ss[1] - $ss[0]);
				}
			} 
			if(!$subtable_in_first_row) {
				$rowstr = substr($this->content, $t['rowstart'],$t['rowend'] - $t['rowstart']);
				//dd($rowstr);
			}

			//echo "<hr>" . $rowstr .  "<hr>";
			$this->table_map[$table_index]['cols'] = $this->countCells($rowstr);

			if($pos < $t['start']){
				$this->addStringToNew(substr($this->content,$pos,$t['start'] - $pos));
			}
			$pos = $t['start'];
			if($t['table2grid']){
				if(empty($t['subtables'])){
					$this->addStringToNew(substr($this->content,$pos,$t['end'] - $pos),1,$table_index);
				} else {
					foreach($t['subtables'] as $st){
						// replace any text prior to the subtable
						if($pos < $st['start']){
							$this->addStringToNew(substr($this->content,$pos,$st['start'] - $pos),1,$table_index);
						}
						// add the entire subtable without replacements
						$pos = $st['start'];
						$this->addStringToNew(substr($this->content,$pos,$st['end'] + 8 - $pos));
						$pos = $st['end'] + 8;
					}
					if($pos < $t['end']){
						$this->addStringToNew(substr($this->content,$pos,$t['end'] - $pos),1,$table_index);
					}	
				}
			} else {
				$this->addStringToNew(substr($this->content,$pos,$t['end'] + 8 - $pos));
			}
			$pos = $t['end'] + 2;
		}
		if($pos < $end){
			$this->addStringToNew(substr($this->content,$pos,$end - $pos));
		}

		//echo "<pre>" . print_r($this->table_map,1) . "</pre>";
		//$processing_time = microtime() - $start_time;
		$article->text = $this->new_content ; /*. "<br>time " . $processing_time . "ms" ;*/
		$document = JFactory::getDocument();
		$document->addStyleSheet(JURI::root() . 'plugins/content/table2grid/table2grid.css');
	}
	
	private function countCells($str)
	{
		$cellcount = substr_count($str,'<td');
		if(!$cellcount){
			$cellcount = substr_count($str,'<th');
		}
		return $cellcount;
	}

	private function findEl(){
		$pos_start_table = strpos($this->content,'<table',$this->offset);
		$pos_start_row = strpos($this->content,'<tr',$this->offset);
		$pos_end_table = strpos($this->content,'</table',$this->offset);
		$pos_class = strpos($this->content,'table2grid',$this->offset);

		if($pos_start_table === false && $pos_end_table === false && $pos_class === false){
			return false;
		}
		
		if($this->t_depth == 0){
			$this->table_map[] = [
				'start' => $pos_start_table,
				'table2grid' => 0,
				'subtables' => [],
				'end' => 0,
				'cols' => 0,
				'rowstart' => $pos_start_row,
				'rowend' => 0,
			];
			$this->offset = $pos_start_table + 7;
			$this->t_depth = 1;
			
		} elseif($this->t_depth == 1){
			// we're in a table - check for class and subtables
			$table_index = count($this->table_map) - 1;
			if($pos_class !== false && ($pos_class < $pos_start_table || $pos_start_table === false )){
				// this table is a table2grid
				$this->table_map[$table_index]['table2grid'] = 1;

				if(!$this->table_map[$table_index]['rowend']){
					$pos_end_row = strpos($this->content,'</tr',$this->offset);
					if($pos_start_table === false || $pos_start_table > $pos_end_row){
						// no subtables are in the way - we have the rowend
						$this->table_map[$table_index]['rowend'] = $pos_end_row;
					}
				}
			}
			if($pos_start_table !== false && $pos_start_table < $pos_end_table){
				// we've found a subtable!
				$this->table_map[$table_index]['subtables'][] = [
					'start' => $pos_start_table,
					'end' => 0,
				];
				$this->t_depth = 2;
				$this->offset = $pos_start_table + 7;
			} else {
				// we've got the end of the table
				$this->table_map[$table_index]['end'] = $pos_end_table + 8;
				$this->t_depth = 0;
				$this->offset = $pos_end_table + 8;
			}
		} else {
			// we're in a subtable - just find the end
			$table_index = count($this->table_map) - 1;
			$subtable_index = count($this->table_map[$table_index]['subtables']) - 1;
			$this->table_map[$table_index]['subtables'][$subtable_index]['end'] = $pos_end_table;
			$this->offset = $pos_end_table + 8;
			$this->t_depth = 1;
			$this->subtable_exiting = 1;
		}
		return true;
	}
	
	private function addStringToNew($str,$table2grid = 0,$table_index = 999)
	{
		if(!$table2grid){
			$this->new_content .= $str;
			return;
		}
		
		$replacements = [
			"<table"	=> "<div",
			"</table>"	=> "</div>",
			"<thead>" 	=> "",
			"</thead>" 	=> "",
			"<tbody>" 	=> "",
			"</tbody>" 	=> "",
			"<tr>" 		=> "",
			"</tr>" 	=> "",
			"<td" 		=> "<div",
			"<th" 		=> "<div",
			"</td>" 		=> "</div>",
			"</th>" 		=> "</div>",
		];
			
		$replacements['table2grid'] = 'table2grid t2gcols-' . $this->table_map[$table_index]['cols'] . ' ' . $this->browser_class;
		
		$placeholders = array_keys($replacements);
		$new_values = array_values($replacements);		
		$this->new_content .= str_replace($placeholders,$new_values,$str);
	}
}