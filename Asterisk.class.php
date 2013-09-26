<?php

/**
 * ASTERISK class
 * @author Scott Griepentrog <sgriepentrog@digium.com>
 *
 * Purpose: download, install, and control Asterisk instance for testing
*/

class Asterisk
{
    public $version;
    public $srcdir;

    public function __construct($version=null)
    {
        $this->version=$version;
        $this->srcdir=false;
    }
    private function exec($cmd)
    {
        $fullcmd='cd '.$this->srcdir.'; ';
        $fullcmd.=$cmd;
        if (substr($cmd,-1,1)!='&')
            $fullcmd.=' 2>&1 >>../'.$this->version.'.log';
        //echo 'Executing: "'.$fullcmd.'"'.PHP_EOL;
        echo shell_exec($fullcmd);
    }
    public function command($cli)
    {
        $this->exec('sudo asterisk -rx "'.$cli.'"');
    }
    public function stop()
    {
        $majorminor=substr($this->version,0,3);
        if ($majorminor=='1.4' || $majorminor=='1.2' || $majorminor=='1.0')
            $this->command('stop now');
        else
            $this->command('core stop now');
    }
    public function start()
    {
        /*
        if (!empty($_SERVER['DISPLAY']))
            $this->exec('xterm -e sudo asterisk -vvvc </dev/null 1>&0 2>&0 &');
        else
            */
            $this->exec('sudo asterisk -vvv </dev/null >>'.$this->version.'.log 2>&1 &');
        sleep(1);
    }
    public function install()
    {
        if (!empty($GLOBALS['asterisk_installed']) && $GLOBALS['asterisk_installed']==$this->version)
            return;

        // if changing versions (or unsure), delete modules directory
        $this->exec('sudo rm -rf /usr/lib/asterisk/modules');

        if (!$this->srcdir)
            throw new Exception('call build() first');

        $this->exec('sudo make install');

        $GLOBALS['asterisk_installed']=$this->version;
    }
    public function build($version=null)
    {
        if ($version)
            $this->version=$version;

        $this->download();
        if (!is_file($this->srcdir.'/config.status'))
            $this->exec('./configure');

        // remove asterisk binary to prove it was built
        if (is_file($this->srcdir.'/main/asterisk'))
            unlink($this->srcdir.'/main/asterisk');
        $this->exec('make');
        if (!is_file($this->srcdir.'/main/asterisk'))
            throw new Exception('Binary missing - check '.$this->version.'.log for errors');
    }
    // uses version, sets srcdir, loads directory srcdir with version
    public function download()
    {
        if (!$this->version)
            throw new Exception('No version specified');

        // is this a X.Y from SVN or a X.Y.Z release tarball?
        if (count(explode('.',$this->version))>2)
        {
            // download release tarball
            $this->srcdir=$this->version.'-release';

            // if directory already exists, presume no d/l needed
            if (is_dir($this->srcdir))
                return;

            $in=fopen('http://downloads.asterisk.org/pub/telephony/asterisk/releases/asterisk-'.$this->version.'.tar.gz','r');
            if (!$in)
                throw new Exception('Unable to download version '.$this->version.': '.print_r($http_response_header,true));

            $tarball=$this->version.'-release.tgz';

            $out=fopen($tarball,'wb');
            if (!$out)
                throw new Exception('Failed to create file: '.$tarball);

            while ($data=fread($in,4096))
                fwrite($out,$data);

            fclose($out);
            fclose($in);

            shell_exec('tar xvfz '.$tarball);
            if (!is_dir('asterisk-'.$this->version))
                throw new Exception('Expected directory not created after tarball extracted');
            shell_exec('mv asterisk-'.$this->version.' '.$this->srcdir);
            if (!is_dir($this->srcdir))
                throw new Exception('Expected directory not present after mv');

            unlink($tarball);
        }
        else
        {
            // pull current code from svn
            throw new Exception('not implemented '.$this->version);
        }
    }
    public static function GetReleases()
    {
            $page=file_get_contents('http://downloads.asterisk.org/pub/telephony/asterisk/releases');

            preg_match_all('/href="asterisk-([0-9.]*).tar.gz/',$page,$matches);
            return($matches[1]);
    }
};
