#!/usr/bin/env php
<?php
/*
 * bookprep.php
 * 20141205
 * re-arrange files and directories and make derivatives to
 * to used as a directory ingest into Islandora Solution Pack Book
 * 20170220
 * add file test and required binaries test mode before run
 * 20170919
 * add delete misc dot/temp files
 * 20180103
 * add discover pdf and rename
 * 20180105
 * fix title entities
 * 20180912
 * add creation of lossey display jp2
 * 
 * 20200421 -- new program bookpreptei.php
 * add discover tei and transcript and rename
 * 20200424 
 * add comment out ocr and hocr (Bridger)- speed improvement!
 * 
 * 20200707 -- new program   bookprepjp2.php
 * add TN and JPG creation from tif during ocr
 * add back ocr and hocr creation for books
 * remove misc text file addition
*/

//------functions-------------------
/*
 * chktess  checks if an install of tesseract is available
 *
*/
function chkTess() {
  global $errorlist;
  $returnValue = '';
  $out=`tesseract -v 2>&1`;
  if (strstr($out,'tesseract 3.')) {
    $returnValue = true;
  }
  else {
    $err="error: Tesseract not available";
    array_push($errorlist, "$err");
  }
  return $returnValue;
}
/*
 * chkKDU  checks if an install of kdu utilities is available
 *
*/
function chkKDU() {
  global $errorlist;
  $returnValue = '';
  $out=`kdu_compress -v 2>&1`;
  if (strstr($out,'version v6')) {
    $returnValue = true;
  }
  else {
    $err="error: kdu_compress/expand not available";
    array_push($errorlist, "$err");
  }
  return $returnValue;
}
/*
 * chkConvert  checks if an install of Imagemagick convert is available
 *
*/
function chkConvert() {
  global $errorlist;
  $returnValue = '';
  $out=`convert -version 2>&1`;
  if (strstr($out,'ImageMagick')) {
    $returnValue = true;
  }
  else {
    $err="error: ImageMagick convert not available";
    array_push($errorlist, "$err");
  }
  return $returnValue;
}
/*
 * chkConvert  checks to see if xmllint is installed
 *
*/
function chkXmllint() {
  global $errorlist;
  $returnValue = '';
  $out=`xmllint --version 2>&1`;
  if (strstr($out,'xmllint: using')) {
    $returnValue = true;
  }
  else {
    $err="error: xmllint not available";
    array_push($errorlist, "$err");
  }
  return $returnValue;
}
/*
 * chkMaindir  checks if the main container directory exists
 * and adds an error if it does not
 *
*/
function chkMaindir($rdir) {
  global $errorlist;
  $returnValue = false;
  if (isDir($rdir)) {
    $returnValue = true;
  }
  else {
    $err="error: Main directory does not exist.";
    array_push($errorlist, "$err");
  }
  return $returnValue;
}
/*
 * chkMeta  checks the metadata file names against directories
 *
 *
*/
function chkMeta($rdir) {
  global $errorlist;
  $xbase='';
  $xmlcount=0;
  $cwd = getcwd();
  chdir($rdir);
  $dfiles = listFiles(".");
  // first loop to read all existing files
  foreach ($dfiles as $dfil) {
    $end = substr($dfil, -4);
    if ($end=='.xml') {
      $xmlcount++;
      //print "testing metadata file: $dfil \n";
      // get basename
      $xbase=basename($dfil,'.xml');
      // check for matching item directory
      if (!isDir($xbase)) {
        $err="error: xml does not have matching directory:$dfil\n";
        array_push($errorlist, "$err");
      }
    }//end if xml
    if (($end=='.xml')&&($xmlcount==0)) {
      $err="error: missing xml \n";
      array_push($errorlist, "$err");
    }
  }//end foreach
  // checking directories to see if they have matching metadata
  $dirfiles = scandir(".");
  foreach ($dirfiles as $d1) {
    // eliminate the dot directories
    if (($d1=='.')||($d1=='..')) continue;
    if (is_dir($d1)) {
      // trim the dir name
      $d1=trim($d1);
      if (!file_exists($d1.'.xml')) {
        $err="error: metadata mismatch-- missing xml file: $d1".".xml";
        array_push($errorlist, "$err");
      }
    }
  }
  chdir($cwd);
  return;
}
/*
* isDir  checks if a directory exists
* and changes into it and changes back to original
*/
function isDir($dir) {
  $cwd = getcwd();
  $returnValue = false;
  if (@chdir($dir)) {
    chdir($cwd);
    $returnValue = true;
  }
  return $returnValue;
}
/*
* colldirexists  checks for directory that holds collection,
* returns error if not there.
*/
function colldirexists($rdir) {
  // exit if no file on command line
  if ((!isset($rdir))||(empty($rdir))) {
    print "*** no directory name given ***, exiting... \n";
    $rdir='';
  }
  //print "******** dir=$rdir\n\n";
  return $rdir;
}
/*
* listFiles  returns an array of all filesnames,
*  in a directory and in subdirectories, all in one list
*
*/
function listFiles( $from = '.') {
  if(! is_dir($from)) return false;
  $files = array();
  $dirs = array( $from);
  while( NULL !== ($dir = array_pop( $dirs))) {
    if( $dh = opendir($dir)) {
      while( false !== ($file = readdir($dh))) {
        if( $file == '.' || $file == '..') continue;
        $path = $dir . '/' . $file;
        if( is_dir($path)) $dirs[] = $path;
        else $files[] = $path;
      }// end while
      closedir($dh);
    }//end if
  }// end if
  return $files;
}
/*
* getNumSep  returns an integer for the number of
* underscores in a basename or gives error for file
*/
function getNumSep($base) {
  global $errorlist;
  // count underscores in filename
  $numsep=substr_count($base, "_");
  if (!$numsep) {
    $err = "error: no underscores: $base";
    array_push($errorlist, "$err");
  }
  if ($numsep>3) {
    $err = "error: too many underscores: $base";
    array_push($errorlist, "$err");
  }
  return $numsep;
}
/*
* getseqdir  returns an integer for an
* page sequence number on the end of a basename
*/
function getseqdir($base) {
    // count underscores in filename
    $numsep=getNumSep($base);
    // break filename on underscores
    $allstr=explode("_",$base);
    if ($numsep==1) {
      // two part name- dir must be parts 0
      $seq=$allstr[1];
      $dirname=$allstr[0];
    }
    elseif ($numsep==2) {
      // three part name- dir must be parts 0,1
      $seq=$allstr[2];
      $dirname=$allstr[0].'_'.$allstr[1];
    }
    elseif ($numsep==3) {
      $seq=$allstr[3];
      // four part name- dir must be parts 0,1,2
      $dirname=$allstr[0]."_".$allstr[1]."_".$allstr[2];
    }
    // convert seq to integer
    print "$seq \n";
    $seq=$seq*1;
    // check for dir already there
    $seqdir=$dirname.'/'.$seq;
  return $seqdir;
}
/*
*  getdirname
*  separates filename and returns part
*  that is supposed to be directory name
*/
function getdirname($base) {
    // count underscores in filename
    $numsep=substr_count($base, "_");
    // break filename on underscores
    $allstr=explode("_",$base);
    if ($numsep==1) {
      // two part name- dir must be parts 0
      $seq=$allstr[1];
      $dirname=$allstr[0];
    }
    elseif ($numsep==2) {
      // three part name- dir must be parts 0,1
      $seq=$allstr[2];
      $dirname=$allstr[0].'_'.$allstr[1];
    }
    elseif ($numsep==3) {
      $seq=$allstr[3];
      // four part name- dir must be parts 0,1,2
      $dirname=$allstr[0]."_".$allstr[1]."_".$allstr[2];
    }
    // convert seq to integer
    $seq=$seq*1;
    if (($seq>=1)&&($seq<=2000)) print "seq = $seq\n";
    // check for dir already there
    $seqdir=$dirname.'/'.$seq;
    if (!isDir($seqdir)) {
      mkdir($seqdir);
      print "made seqdir= $seqdir \n";
    }
  return $dirname;
}
/*
*  getmeta   returns either MODS or DC
*/
function getmeta($xmlfile) {
  $meta='MODS';
  // check for kind of metadata, DC or MODS
  $xml = file_get_contents("$xmlfile");
  $sxe = new SimpleXMLElement($xml);
  $namespaces = $sxe->getDocNamespaces(TRUE);
  // mods 3.2
  if (isset($namespaces['mods'])) $meta="MODS";
  // mods 3.5
  if (isset($namespaces[''])) $meta="MODS";
  if (isset($namespaces['dc'])) $meta="DC";
  return $meta;
}
/*
 * pLine   prints a message between lines of dashes
*/
function pLine($message="test") {
  print "*--------------------------------------------\n";
  print "*  $message \n";
  print "*--------------------------------------------\n";
  return;
}
/*
 * mkDeriv  takes the current OBJ.tif and makes TN and JPG derivatives
*/
function mkImgDeriv() {
  // create TN and JPG
  $jpgcvt = "convert -size 600x800 OBJ.tif -resize 600x800 -quality 75 JPG.jpg";
  print "Converting temp tif to jpg\n";
  exec($jpgcvt);
  $tncvt = "convert -size 200x200 JPG.jpg -resize 200x200 -quality 85 TN.jpg";
  print "Converting jpg to tn\n";
  exec($tncvt);
  return;
}
/*
* gettitle  retruns the title of a book,
depending on the metadata
*/
function gettitle($xmlfile,$meta) {
  $xml = file_get_contents("$xmlfile");
  $sxe = new SimpleXMLElement($xml);
  if ($meta=='DC') $booktitle = $sxe->title;
  else $booktitle = $sxe->titleInfo->title;
  return $booktitle;
}
/*
 * paramErr  shows parameter reminder
*/ 
function paramErr() {
  print "\n";
  print "usage: bookprep.php directoryname destination-image-type:(tif|jp2)\n";
  print "Error **  missing parameters*** \n";
  return; 
}
/*
 * mkJP2  non-lossey jp2 for OBJ with kdu_compress
*/ 
function mkJP2() {
  $args = 'Creversible=yes -rate -,1,0.5,0.25 Clevels=5';
  $convertcommand="kdu_compress -i OBJ.tif -o OBJ.jp2 $args ";
  print "Converting tif to jp2\n";
  exec($convertcommand);
  return; 
}
/*
 * mkTIF  convert jp2 to tif with kdu_expand
*/ 
function mkTIF() {
  $convertcommand="kdu_expand -i OBJ.jp2 -o OBJ.tif ";
  print "Converting jp2 to tif\n";
  exec($convertcommand);
  return; 
}
/*
 * mkLosseyJP2  convert tif to lossey jp2
*/ 
function mkLosseyJP2() {
  $args= '-rate 0.5 Clayers=1 Clevels=7 "Cprecincts={256,256},{256,256},{256,256},{128,128},{128,128},{64,64},{64,64},{32,32},{16,16}" "Corder=RPCL" "ORGgen_plt=yes" "ORGtparts=R" "Cblk={32,32}" Cuse_sop=yes';
  $convertcommand="kdu_compress -i OBJ.tif -o JP2.jp2 $args ";
  print "Converting tif to display jp2\n";
  exec($convertcommand);
  return; 
}
//------------- begin main-----------------
$newworkflow=0;
$xpdf=$xtei=$rdir=$numsep=$xnew=$new=$tif=$rep=$dfilp=$newp='';
$errorlist = array();
//get parameters from command line
if (isset($argv[1])) $rdir=$argv[1];
else {
  paramErr();
  exit();
}
if (isset($argv[2])) {
  if (($argv[2]=='tif')||($argv[2]=='jp2')) $totype=$argv[2];
  else {
    paramErr();
    exit();
  }
} //end if
else {
  paramErr();
  exit();
}

// ---------------
if((chkConvert())&&(chkXmllint())&&(chkKDU())&&(chkTess())&&(chkMaindir($rdir))) {
  // running basic system checks
  chkMeta($rdir);
}
if(count($errorlist)>=1) {
  pLine("These errors exist, please fix and rerun. ");
  foreach($errorlist as $err) {
    print "$err\n";
  }
  pLine(" Bookprepjp2 is exiting.");
  exit();
}
pLine(" There are no errors, bookprep will be able to start the processing.");
print "* Continue?: (Y or any key to exit) \n";
$input=fgetc(STDIN);
if (($input!='y')&&($input!='Y')) {
  pLine(" Bookprepjp2 is exiting.");
  exit();
} //else will continue below
pLine(" Bookprepjp2 is now continuing");
// change to dir and read filenames
chdir($rdir);
exec("rename '_i.jp2' '.jp2' */*_i.jp2");
exec("rename '_p.jp2' '.pre' */*_p.jp2");
$dfiles = listFiles(".");
// first loop to read all existing files
foreach ($dfiles as $dfil) {
  $dirname=$seq=$seqdir=$xbase=$base=$xnew=$new=$tfile=$tnew='';
  // eliminate the dot directories
  if (($dfil=='.')||($dfil=='..')) continue;
  // delete .DS_Store and ._*
  $test = basename($dfil);
  if (($test == '.DS_Store')||(substr($test, 0, 2)=='._')) {
    unlink($dfil);
    continue;
  }
  print "current file=$dfil \n";
  //check extension
  $end = substr($dfil, -4);
  if ($end=='.xml') {
    // get basename
    $xbase=basename($dfil,'.xml');
    // check for kind of metadata, DC or MODS
    $meta=getmeta($dfil);
    //make new location
    $xnew='./'.$xbase.'/'.$meta.'.xml';
    if(!file_exists($xnew)) copy($dfil,$xnew);
    print "copying: $dfil \n  to $xnew\n";
    //=========== also check for existing pdf file
    $xpdf='./'.$xbase.'.pdf';
    print "Checking for $xpdf\n";
    $xpdfnew='./'.$xbase.'/PDF.pdf';
    if(file_exists($xpdf)) {
      rename($xpdf,$xpdfnew);
      print "Renaming $xpdf to $xpdfnew\n";
    }
    //========= also check for existing .tei file
    $xtei='./'.$xbase.'.tei';
    print "Checking for $xtei\n";
    $xteinew=$xbase.'/TEI.xml';
    if(file_exists($xtei)) {
      rename($xtei,$xteinew);
      print "Renaming $xtei to $xteinew\n";
    }
    //========= also check for existing transcript.txt file
    $xtscp='./'.$xbase.'.txt';
    print "Checking for $xtscp\n";
    $xtscpnew=$xbase.'/TRANSCRIPT.txt';
    if(file_exists($xtscp)) {
      rename($xtscp,$xtscpnew);
      print "Renaming $xtscp to $xtscpnew\n";
    }
  }// end if xml
  elseif ($end=='.jp2') {
    $fromtype='jp2';
  }
  elseif ($end=='.tif') {
    $fromtype='tif';
  }
  else $fromtype='';
  if (($end=='.jp2') || ($end=='.tif')) {
    if ($end=='.jp2') {
      // modify copy of dfil for pre extension
      $dfilp=$dfil;
      $dfilp = str_replace('.jp2','.pre',$dfilp);
      print "**** dfilp = $dfilp \n";
    }
    // get basename
    $base=basename($dfil,$end);
    $seqdir=getseqdir($base);
    $dirname=getdirname($base);
    print "Now working with $dirname...\n";
    // find seq
    $s=explode('/',$seqdir);
    $seq=$s[1];
    $newdir='./'.$seqdir;
    $new='./'.$seqdir."/".'OBJ'.$end;
    // setup new preserve name
    $newp='./'.$seqdir."/".'PRESERVE'.'.jp2';
    // what is xbase xml of this image
    $thisxml=getdirname($base).".xml";
    // get booktitle specific to this image
    $booktitle=gettitle($thisxml,$meta);
    // encode entities
    $booktitle=htmlspecialchars($booktitle);
    // make mods.xml
    $pagexml=<<<EOL
<?xml version="1.0" encoding="UTF-8"?>
<mods:mods xmlns:mods="http://www.loc.gov/mods/v3" xmlns="http://www.loc.gov/mods/v3">
  <mods:titleInfo>
    <mods:title>$booktitle : page $seq</mods:title>
  </mods:titleInfo>
</mods:mods>
EOL;
// switch contexts to fix syntax highlighting
?>
<?php
    $mfile=$seqdir."/"."MODS.xml";
    print "Writing MODS.xml\n";
    file_put_contents($mfile, $pagexml);
    // rename page image to OBJ
    if(!file_exists($new)) {
      rename($dfil,$new);
      print "renaming:  $dfil \n   to : $new\n";
    }
    else {
      print "$new is already in destination, ok.\n";
    }
    // rename pre image to PRESERVE
    if(!file_exists($newp)) {
      rename($dfilp,$newp);
      print "renaming:  $dfilp   to : $newp \n";
    }
    else {
      print "$newp is already in destination, ok.\n";
    }
    // change into new page dir, remembering previous
    $cwd = getcwd();
    pLine("Changing to directory: $newdir");
    chdir($newdir);
    // do conversion if needed
    if (($fromtype=='tif')&&($totype=='jp2')) {
      mkJP2();
    }// end if tif2jp2
    if ($fromtype=='jp2') {
      // create tif from jp2
      mkTIF();
    }// end if fromtype=jp2
    // create display JP2
    mkLosseyJP2();
    // create OCR
    print "Creating OCR...... \n";
    $tesscommand="tesseract OBJ.tif OCR -l eng";
    exec($tesscommand);
    //create HOCR
    print "Creating HOCR...... \n";
    $tesscommand="tesseract OBJ.tif HOCR -l eng hocr";
    exec($tesscommand);
    // remove doctype if it is there using xmllint
    shell_exec("xmllint --dropdtd --xmlout HOCR.hocr --output HOCR.html");
    exec("rm -f HOCR.hocr");
    // delete redundant text file if it exists
    if (is_file('HOCR.txt')) exec("rm -f HOCR.txt");
    mkImgDeriv();
    // if dest is tif
    if ($totype=='tif') {
      // delete the OBJ.jp2
      if (is_file('OBJ.jp2'))  exec("rm -f OBJ.jp2");
    }// end if totype is tif
    // if the OCR and HOCR are there, delete the tif, unless it is the totype
    if ((is_file("OCR.txt"))&&($totype!='tif'))  exec("rm -f OBJ.tif");
    // if new workflow, rename to PRESERVE.jp2

    // change back
    chdir($cwd);
    //
  }//end else is tif
  //chdir('..');
}//end foreach
pLine(" Bookprepjp2 has finished.");
unset($dfiles);
?>
