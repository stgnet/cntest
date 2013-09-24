<?php
    // Codec negotiation testing

function __autoload($class) {
    require_once $class.'.class.php';
}

//print_r(Asterisk::GetReleases());

$ast=new Asterisk('1.8.20.1');
$ast->download();
$ast->build();
$ast->install();

$caller='3175551212@127.0.0.1';
$callee='2565551212@127.0.0.1';


$sip1=new SIP($caller);
$sip2=new SIP($callee);

file_put_contents('/etc/asterisk/modules.conf',"
[modules]
autoload=yes
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
while ($timeout)
{
    $msg=$sip1->read();

    if (!$msg)
        $msg=$sip2->read();

    if (!$msg)
    {
        sleep(1);
        $timeout--;
        continue;
    }
}
$sip1->Cancel();

sleep(3);

$ast->command('core stop gracefully');

