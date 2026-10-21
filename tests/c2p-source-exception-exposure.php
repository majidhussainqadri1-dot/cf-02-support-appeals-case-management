<?php
declare(strict_types=1);
/**
 * Repository-wide PHP source privacy guard:
 * do not hand Throwable objects to action hooks, debug-loggers, or telemetry.
 * This scan intentionally looks for concrete raw-exception exposures rather
 * than claiming to prove the absence of all possible sensitive disclosures.
 */
$root=dirname(__DIR__).'/src';
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
$checked=0;$findings=[];
foreach($it as $file){
    if(!$file->isFile()||$file->getExtension()!=='php')continue;
    $checked++;
    $code=(string)file_get_contents($file->getPathname());
    $rows=explode("\n",$code);
    foreach($rows as $i=>$line){
        if(preg_match('/[\\x27\\x22]error[\\x27\\x22]\\s*=>\\s*\\$(?:error|exception)\\b/', $line)===1 ||
           preg_match('/\\b(?:error_log|var_dump|print_r)\\s*\\(\\s*\\$(?:error|exception)\\b/', $line)===1){
            $findings[]=$file->getFilename().':'.($i+1);
        }
    }
}
printf("CF-02 source exception disclosure: %d PHP source files scanned; %d raw-exception reporting patterns detected.\n",$checked,count($findings));
foreach($findings as $location)fwrite(STDERR,"FAIL raw exception reporting at: ".$location."\n");
exit(($checked>=150 && $findings===[])?0:1);
