<?php
    // Codec negotiation testing
/*
Codecs known to asterisk (1.8):
         g723   gsm  ulaw  alaw g726aal2 adpcm  slin lpc10  g729 speex  ilbc  g726  g722 siren7 siren14 slin16  g719 speex16 testlaw
    g723     -     -     -     -        -     -     -     -     -     -     -     -     -      -       -      -     -       -       -
     gsm     -     -  3000  2001     6999  3000  2000  9999     -     - 38994  7999  3000      -       -   6000     -       -    2001
    ulaw     -  6999     -     1     5999  2000  1000  8999     -     - 37994  6999  2000      -       -   5000     -       -    1001
    alaw     -  6999     1     -     5999  2000  1000  8999     -     - 37994  6999  2000      -       -   5000     -       -    1001
g726aal2     -  8998  3999  3000        -  3999  2999 10998     -     - 39993  8998  3999      -       -   6999     -       -    3000
   adpcm     -  6000  1001     2     5000     -     1  8000     -     - 36995  6000  1001      -       -   4001     -       -       2
    slin     -  5999  1000     1     4999  1000     -  7999     -     - 36994  5999  1000      -       -   4000     -       -       1
   lpc10     - 10999  6000  5001     9999  6000  5000     -     -     - 41994 10999  6000      -       -   9000     -       -    5001
    g729     -     -     -     -        -     -     -     -     -     -     -     -     -      -       -      -     -       -       -
   speex     -     -     -     -        -     -     -     -     -     -     -     -     -      -       -      -     -       -       -
    ilbc     - 12998  7999  7000    11998  7999  6999 14998     -     -     - 12998  7999      -       -  10999     -       -    7000
    g726     -  9998  4999  4000     8998  4999  3999 11998     -     - 40993     -  4999      -       -   7999     -       -    4000
    g722     -  9999  5000  4001     8999  5000  4000 11999     -     - 40994  9999     -      -       -   3000     -       -    4001
  siren7     -     -     -     -        -     -     -     -     -     -     -     -     -      -       -      -     -       -       -
 siren14     -     -     -     -        -     -     -     -     -     -     -     -     -      -       -      -     -       -       -
  slin16     - 13999  9000  8001    12999  9000  8000 15999     -     - 44994 13999  4000      -       -      -     -       -    8001
    g719     -     -     -     -        -     -     -     -     -     -     -     -     -      -       -      -     -       -       -
 speex16     -     -     -     -        -     -     -     -     -     -     -     -     -      -       -      -     -       -       -
 testlaw     -  6000  1001     2     5000  1001     1  8000     -     - 36995  6000  1001      -       -   4001     -       -       -

Codecs defined in sdp handling:
 define('ULAW',0);
 define('ALAW',8);

 define('PCMU',0);
 define('GSM',3);
 define('G723',4);
 define('PCMA',8);
 define('G722',9);
 define('G728',15);
 define('G729',18);
 define('DTMF',101);
 define('G726',111);

Making an assumption that there is not special handling for specific codecs:
1) g729 can be used to test codec when module not loaded
2) Codecs always available: ulaw, alaw, gsm

Thus: codecs to test are: ulaw, alaw, gsm, g729 (without loading g729 support)

This testing is currently 100% ignoring video codecs

*/

function __autoload($class) {
    require_once $class.'.class.php';
}

//print_r(Asterisk::GetReleases());

/**
 * $version = any release or svn branch
 * $call = codec array to initiate call with
 * $allow1 = codec array that caller sip.conf allows
 * $allow2 = codec array that callee sip.conf allows
 * $direct = setting of directrtpsetup 'yes' or 'no'
 *
 * returns codec array that was invite'd to callee
*/
function CodecTest($version,$call,$allow1,$allow2,$direct)
{
    $ast=new Asterisk($version);
    $ast->download();
    $ast->build();
    $ast->install();

    $caller='3175551212@127.0.0.1';
    $callee='2565551212@127.0.0.1';

    $sip1=new SIP($caller);
    $sip2=new SIP($callee);

    if (!is_dir('/etc/asterisk'))
        throw new Exception('/etc/asterisk directory does not exist');

    // insure that this user can modify /etc/asterisk
    shell_exec('sudo chown `whoami` /etc/asterisk');

    file_put_contents('/etc/asterisk/modules.conf',"
[modules]
autoload=yes
");

    if (!is_file('/etc/asterisk/modules.conf'))
        throw new Exception('Unable to write /etc/asterisk/modules.conf');

    file_put_contents('/etc/asterisk/logger.conf',"
[general]
[logfiles]
console => notice,warning,error
full => notice,warning,error,verbose
");

    file_put_contents('/etc/asterisk/sip.conf',"
[general]
context=default
allowoverlap=no
udpbindaddr=0.0.0.0
tcpenable=no
transport=udp
srvlookup=no
directmedia=yes
directrtpsetup=no

[caller]
type=friend
host=127.0.0.1
port={$sip1->port}
dtmfmode=rfc2833
nat=no
qualify=no
allow=ulaw
allow=alaw
allow=g729
directmedia=yes

[callee]
type=friend
host=127.0.0.1
port={$sip2->port}
dtmfmode=rfc2833
nat=no
qualify=no
allow=ulaw
allow=alaw
allow=g729
directmedia=yes
");

    file_put_contents('/etc/asterisk/extensions.conf',"
[general]
static=yes
writeprotect=no
clearglobalvars=no
[globals]
[default]
exten => _317XXXXXXX,1,Dial(SIP/\${EXTEN}@caller)
exten => _256XXXXXXX,1,Dial(SIP/\${EXTEN}@callee)
");


    $ast->start();

    $sip1->Invite($callee);

    $timeout=3;
    while ($timeout--)
    {
        $msg=$sip1->read();
        if ($msg)
        {
            $exp=explode(ODOA,$msg);
            echo 'Reply to caller: '.$exp[0]."\n";
            continue;
        }

        $msg=$sip2->read();
        if ($msg)
        {
            echo $msg;
            continue;
        }

        sleep(1);
    }
    $sip1->Cancel();

    sleep(3);

    $ast->command('core stop now');
}

