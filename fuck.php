#!/usr/bin/php
<?php
$opts = yago("-d,--debug,-h,--help,-c,--clean");

// Check for debug flag.
if(array_key_exists("-d",$opts['flags']) or
  array_key_exists("--debug",$opts['flags'])){
  $debug = true;
  error_reporting(-1);
}else{
  $debug = false;
  error_reporting(0);
}

// Check for help flag.
if(array_key_exists("-h",$opts['flags']) or
  array_key_exists("--help",$opts['flags'])){
  usage();
  die();
}

// Check for clean flag.
if(array_key_exists("-c",$opts['flags']) or
  array_key_exists("--clean",$opts['flags'])){
  $clean = TRUE;
} else {
  $clean = FALSE;
}

$input = $opts['operands'][1];

// Command table to a table system that makes more sense for internal debugging
$commands = array(
  "fuck" => "table_init",
  "fucking" => "table_set",
  "fucked" => "table_mod",
  "unfucking" => "table_input",
  "unfucked" => "table_output",
  "motherfuck" => "label_define",
  "motherfucking" => "label_jump",
  "motherfucked" => "label_branch",
);

// Convert the table to the clean definition.
if($clean){
  $clean_commands = array();
  foreach($commands as $command){
    $clean_commands[$command] = $command;
  }
  $commands = $clean_commands;
}

// The argument types of the commands
$argtable = array(
  "table_init" => array("raw"),
  "table_set" => array("var","raw"),
  "table_mod" => array("var","var"),
  "table_input" => array("var"),
  "table_output" => array("var"),
  "label_define" => array("raw"),
  "label_jump" => array("label"),
  "label_branch" => array("var","label")
);

if(is_file($input)){
  // Read the data, remove trailing newline, remove newlines, and then split by
  // word into an array.
  // TODO: Make this into a preg_match_all().
  $data = explode(" ",
    implode(" ",
      explode("\n",
        rtrim(file_get_contents($input))
      )
    )
  );
} else {
  die("fuck: fatal error: no input files.\nrun terminated.\n");
}

// Run through the data once to check for labels
$labels = array();
foreach($data as $pc => $line){
  // Labels are case insensitive.
  $currentline = strtolower($line);
  // if the command
  if(isset($commands[$currentline]) and
    $commands[$currentline] == "label_define"){
    // Find all of the label_define commands, and put argument in an array.
    // TODO: Do more debugging to check if by running from this argument, one
    // can use a command op as a argument.
    // Check label_jump and label_branch too
    $labels[$data[$pc+1]]=$pc;
  }
}
// Program Counter
$pc = 0;
// Array for variables
$vars = array();
// Argument stack
$argstack = array();
// Current Operation
$currentop = null;

// While program counter is not at the end
while($pc < count($data)){
  // case insensitivity
  $currentline = strtolower($data[$pc]);
  if($currentop){ // looking for arguments for the current operation.
    // Determine type from the argument type table.
    $type = $argtable[$currentop][count($argstack)];
    if($type == "raw"){
      // If the argument is raw, then put the next thing on the stack.
      // raw arguments are conditionally case insensitive.
      $argstack[] = $data[$pc];
    } elseif($type == "var"){
      // If the argument is var, then put the next var on the stack.
      if(isset($vars[$currentline])){
        $argstack[] = $currentline;
        if($debug){echo "Adding var to argstack.\n";}
      }
    } elseif($type == "label"){
      // If the argument is label, then put the next label on the stack.
      if(isset($labels[$currentline])){
        $argstack[] = $currentline;
        if($debug){echo "Adding label to argstack.\n";}
      }
    }
    // If the argument stack is ready for the current operation
    if(count($argstack)==count($argtable[$currentop])){
      // Execute the operation, and clear the operation and argument stack.
      $currentop($argstack);
      $currentop = null;
      $argstack = null;
    }
  } else { // looking for a current operation.
    // if a valid command
    if(isset($commands[$currentline])){
      $currentop = $commands[$currentline];
    }
  }
  // increment the program counter.
  $pc++;
}
// echo out newline at end of file
echo "\n";

// These functions are thoroughly described in the readme file. All functions
// only take in arguments from the argument stack, and do not return anything,
// as they access everything globally. Usually, this would be a bad idea, but
// the language is so small, it really does not matter and lowers the amount of
// overhead being used.
function table_init($args){
  global $vars,$pc,$debug;
  if($debug){echo "table_init ".implode(" ",$args)."\n";}
  // Specifically using empty string as oppsed to nill to avoid having to check
  // with array_key_exists().
  // TODO: Change to array_key_exists() for speed.
  // Variable names are case insensitive.
  $vars[strtolower($args[0])] = "";
}
function table_set($args){
  global $vars,$pc,$debug;
  if($debug){echo "table_set ".implode(" ",$args)."\n";}
  if(!isset($vars[$args[0]])){
    die("ERROR[$pc]: var args[0] <".$args[0]."> is not initialized.\n");
  }
  // Raw string data is case sensitive.
  $vars[$args[0]] = $args[1];
}
function table_mod($args){
  global $vars,$pc,$debug;
  if($debug){echo "table_mod ".implode(" ",$args)."\n";}
  if(!isset($vars[$args[0]])){
    die("ERROR[$pc]: var args[0] <".$args[0]."> is not initialized.\n");
  }
  if(!isset($vars[$args[1]])){
    die("ERROR[$pc]: var args[1] <".$args[1]."> is not initialized.\n");
  }
  // TODO: Make is_numeric more portable if the language changes to something
  // lower level, like C.
  // If both arguments are numbers:
  if(is_numeric($vars[$args[0]]) and is_numeric($vars[$args[1]])){
    // Treat them as numbers.
    $vars[$args[0]] += $vars[$args[1]];
  } else {
    // Treat them as strings.
    $vars[$args[0]] .= " ".$vars[$args[1]];
  }
}
function table_input($args){
  global $vars,$pc,$debug;
  if($debug){echo "table_input ".implode(" ",$args)."\n";}
  if(!isset($vars[$args[0]])){
    die("ERROR[$pc]: var args[0] <".$args[0]."> is not initialized.\n");
  }
  // using readline over fgets because this script is very linux like.
  // TODO: change over to fgets for portability, or, implement the
  // readline_history features.
  $vars[$args[0]] = readline("");
}
function table_output($args){
  global $vars,$pc,$debug;
  if($debug){echo "table_output ".implode(" ",$args)."\n";}
  if(!isset($vars[$args[0]])){
    die("ERROR[$pc]: var args[0] <".$args[0]."> is not initialized.\n");
  }
  // The system took in \n as actual data, so it needs to be translated to a
  // newline.
  echo preg_replace('@\\\\n@',"\n",$vars[$args[0]]);
}
function label_define($args){
  global $labels,$pc,$debug;
  if($debug){echo "label_define ".implode(" ",$args)."\n";}
  // This function does not actually do anything, but it is important to render
  // it so that the system does not treat it or it's argument as a raw
  // arguments.
}
function label_jump($args){
  global $labels,$pc,$debug;
  if($debug){echo "label_jump ".implode(" ",$args)."\n";}
  if(!isset($labels[$args[0]])){
    die("ERROR[$pc]: label args[0] <".$args[0]."> is not initialized.\n");
  }
  // Change the program counter to the defined label's argument.
  $pc = $labels[$args[0]]+1;
}
function label_branch($args){
  global $labels,$vars,$pc,$debug;
  if($debug){echo "label_branch ".implode(" ",$args)."\n";}
  if(!isset($vars[$args[0]])){
    die("ERROR[$pc]: var args[0] <".$args[0]."> is not initialized.\n");
  }
  if(!isset($labels[$args[1]])){
    die("ERROR[$pc]: label args[1] <".$args[1]."> is not initialized.\n");
  }
  // Change the program counter to the defined label if variable does not equal
  // zero.
  if($vars[$args[0]]!="0"){
    $pc = $labels[$args[1]]+1;
  }
}

function usage(){
?>
 Usage: fuck [options] input
 Options:
   -d|--debug               Enable debug mode to show how the code is being
                            rendered.
   -c|--clean               Run the code using the clean reserved words.
   -h|--help                Display this information
<?php
}


/**
 * Yet Another Get Options
 *
 * This function will provide a simple alternative to *nix's getopt that has
 * some extended features found in GNU's getopts.
 *
 * @author josefnpat <seppi@josefnpat.com>
 *
 * @param $raw_flags
 *   This string represents the flags that should be parsed by this function.
 *   The flags that this function will look for is seperated by commas. E.g.;
 *   "-d,--debug,-n!,--name!"
 *   If flag has an exclamation mark (`!`) at the end, the function will
 *   assume that the flag is expecting an argument.
 * @param $argv|NULL
 *   This is the argument vector provided when a script is invoked by php-cli.
 *   By default, it will use the global `$argv`, but can be overridden by
 *   passing an array of arguments.
 *
 * @return
 *   The returned data will consist of a nexted associative array containing:
 *   - operands: an array of arguments that are not flags or flag operands
 *   - flags: an associtave array of flags that were found. If the flag is;
 *     - expecting an argument: the value will be assigned.
 *     - not expecting an arguement: the value will be null.
 */


function yago($raw_flags,$argv=NULL){
  if($argv === NULL){
    global $argv;
  }
  $stack = array();
  $stack['operands'] = array();
  $stack['flags'] = array();
  $search_flags = explode(",",$raw_flags);
  while(!empty($argv)){
    $arg = array_shift($argv);
    if(in_array($arg,$search_flags)){
      $stack['flags'][$arg] = NULL;
    } elseif(in_array("$arg!",$search_flags)){
      $stack['flags'][$arg] = array_shift($argv);
    } elseif(substr($arg,0,1)!="-") {
      $stack['operands'][] = $arg;
    }
  }
  return $stack;
}