<?php

/**
 * SIP class
 * @author Scott Griepentrog <sgriepentrog@digium.com>
 *
 * Purpose: simulate invite/sdp for codec negotiation testing
*/

/*
               PT   encoding    media type  clock rate   channels
                    name                    (Hz)
               ___________________________________________________
               0    PCMU        A            8,000       1
               1    reserved    A
               2    reserved    A
               3    GSM         A            8,000       1
               4    G723        A            8,000       1
               5    DVI4        A            8,000       1
               6    DVI4        A           16,000       1
               7    LPC         A            8,000       1
               8    PCMA        A            8,000       1
               9    G722        A            8,000       1
               10   L16         A           44,100       2
               11   L16         A           44,100       1
               12   QCELP       A            8,000       1
               13   CN          A            8,000       1
               14   MPA         A           90,000       (see text)
               15   G728        A            8,000       1
               16   DVI4        A           11,025       1
               17   DVI4        A           22,050       1
               18   G729        A            8,000       1
               19   reserved    A
               20   unassigned  A
               21   unassigned  A
               22   unassigned  A
               23   unassigned  A
               dyn  G726-40     A            8,000       1
               dyn  G726-32     A            8,000       1
               dyn  G726-24     A            8,000       1
               dyn  G726-16     A            8,000       1
               dyn  G729D       A            8,000       1
               dyn  G729E       A            8,000       1
               dyn  GSM-EFR     A            8,000       1
               dyn  L8          A            var.        var.
               dyn  RED         A                        (see text)
               dyn  VDVI        A            var.        1

               24      unassigned  V
               25      CelB        V           90,000
               26      JPEG        V           90,000
               27      unassigned  V
               28      nv          V           90,000
               29      unassigned  V
               30      unassigned  V
               31      H261        V           90,000
               32      MPV         V           90,000
               33      MP2T        AV          90,000
               34      H263        V           90,000
               35-71   unassigned  ?
               72-76   reserved    N/A         N/A
               77-95   unassigned  ?
               96-127  dynamic     ?
               dyn     H263-1998   V           90,000
v=0
o=root 828175017 828175017 IN IP4 192.168.100.11
s=Asterisk PBX 11.5.1
c=IN IP4 192.168.100.11
t=0 0
m=audio 10312 RTP/AVP 0 8 9 18 4 111 101
a=rtpmap:0 PCMU/8000
a=rtpmap:8 PCMA/8000
a=rtpmap:9 G722/8000
a=rtpmap:18 G729/8000
a=fmtp:18 annexb=no
a=rtpmap:4 G723/8000
a=fmtp:4 annexa=no
a=rtpmap:111 G726-32/8000
a=rtpmap:101 telephone-event/8000
a=fmtp:101 0-16
a=ptime:20
a=sendrecv

*/
define('ODOA',"\r\n");

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


class SIP
{
    private $sock;
    private $from;
    private $from_number;
    private $from_domain;
    private $to;
    private $to_number;
    private $to_domain;
    private $callid;
    private $branch;
    private $tag;
    public $ip;
    public $port;

    public $rtpmap=array(
        0 => 'PCMU/8000',
        3 => 'GSM/8000',
        4 => 'G723/8000',
        8 => 'PCMA/8000',
        9 => 'G722/8000',
        15 => 'G728/8000',
        18 => 'G729/8000',
        101 => 'telephone-event/8000',
        111 => 'G726/8000',
    );

    public $fmtp=array(
        4 => 'annexa=no',
        18 => 'annexb=no',
        101 => '0-16',
    );

    private function CreateSdp($codecs)
    {
        $rtp=array();
        foreach ($codecs as $code)
        {
            if (empty($this->rtpmap[$code]))
                throw new Exception('RTPMAP not defined for codec #'.$code);
            $rtp[$code]=$this->rtpmap[$code];
        }
        if (empty($rtp[DTMF]))
            $rtp[DTMF]=$this->rtpmap[DTMF];

        $sdp='';
        $sdp.='v=0'.ODOA;
        $sdp.='o=root '.time().' '.time().' IN IP4 '.$this->ip.ODOA;
        $sdp.='s=PHP SIP'.ODOA;
        $sdp.='c=IN IP4 '.$this->ip.ODOA;
        $sdp.='t=0 0'.ODOA;
        $sdp.='m=audio '.($this->port+1).' RTP/AVP';
        foreach ($rtp as $code => $value)
            $sdp.=' '.$code;
        $sdp.=ODOA;
        foreach ($rtp as $code => $value)
        {
            $sdp.='a=rtpmap:'.$code.' '.$value.ODOA;
            if (!empty($this->fmtp[$code]))
                $sdp.='a=fmtp:'.$code.' '.$this->fmtp[$code].ODOA;
        }
        $sdp.="a=silenceSupp:off - - - -".ODOA;
        $sdp.='a=ptime:20'.ODOA;
        $sdp.='a=sendrecv'.ODOA;

        return($sdp);
    }
    public function __construct($from)
    {
        $this->from=$from;
        $split=explode('@',$from);
        $this->from_number=$split[0];
        $this->from_domain=$split[1];
        $this->sock=socket_create(AF_INET,SOCK_DGRAM,0);
        if (!$this->sock)
            throw new Exception('unable to create socket: '.socket_strerror(socket_last_error()));
        socket_bind($this->sock,$this->from_domain);
        socket_getsockname($this->sock,$ip,$port);
        $this->ip=$ip;
        $this->port=$port;
        socket_set_nonblock($this->sock);
    }
    public function read()
    {
        $from_ip='';
        $from_port=0;
        $data='';
        $bytes=@socket_recvfrom($this->sock,$data,1024,0,$from_ip,$from_port);
        if (!$bytes) return(false);

        return($data);
    }
    private function BasicHeader($method)
    {
        $sip=$method.' sip:'.$this->to.' SIP/2.0'.ODOA;
        $sip.='Via: SIP/2.0/UDP '.$this->ip.':'.$this->port.';branch='.$this->branch.ODOA;
        $sip.='Max-Forwards: 70'.ODOA;
        $sip.='From: "'.$this->from_number.'" <sip:'.$this->from.'>;tag='.$this->tag.ODOA;
        $sip.='To: <sip:'.$this->to.'>'.ODOA;
        $sip.='Contact: <sip:'.$this->from.':'.$this->port.'>'.ODOA;
        $sip.='Call-ID: '.$this->callid.ODOA;
        $sip.='CSeq: 102 '.$method.ODOA;
        $sip.='User-Agent: PHP SIP'.ODOA;
        $sip.='Date: '.date('r').ODOA;
        return($sip);
    }
    public function invite($to,$codecs=false)
    {
        if (!$codecs)
            $codecs=array(ULAW,ALAW,DTMF);

        $this->branch='z9hG4bK'.substr(md5(time()),15);
        $this->tag=substr(md5('tag'.time()),-8);
        $this->callid=md5($to.time()).'@'.$this->ip.':'.$this->port;

        $this->to=$to;
        $split=explode('@',$to);
        $this->to_number=$split[0];
        $this->to_domain=$split[1];

        date_default_timezone_set('UTC');
        $sdp=$this->CreateSdp($codecs);
        $sip=$this->BasicHeader('INVITE');
        $sip.='Allow: INVITE, ACK, CANCEL, OPTIONS, BYE, REFER'.ODOA;
        $sip.='Content-Type: application/sdp'.ODOA;
        $sip.='Content-Length: '.strlen($sdp).ODOA;
        $sip.=ODOA;
        $sip.=$sdp;

        socket_sendto($this->sock,$sip,strlen($sip),0,$this->to_domain,5060);
    }
    public function cancel()
    {
        // must have previously called invite to set up vars
        $sip=$this->BasicHeader('CANCEL');
        $sip.='Content-Length: 0'.ODOA;

         socket_sendto($this->sock,$sip,strlen($sip),0,$this->to_domain,5060);

    }
};
