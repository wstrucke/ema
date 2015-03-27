<?php

/*
function application_start ()
{
    global $_APP;

    // if data file exists, load application
    //   variables
    if (file_exists($this->applicationFile))
    {
        // read data file
        $file = fopen($this->applicationFile, "r");
        if ($file)
        {
            $data = fread($file,
                filesize($this->applicationFile));
            fclose($file);
        }

        // build application variables from
        //   data file
        $_APP = unserialize($data);
    }
}

function application_end ()
{
    global $_APP;

    // write application data to file
    $data = serialize($_APP);
    $file = fopen($this->applicationFile, "w");
    if ($file)
    {
			fwrite($file, $data);
			fclose($file);
    } else {
			#echo 'An error has occurred while attemping to update the application data file.  Please contact the website administrator.';
		}
}
*/

?>