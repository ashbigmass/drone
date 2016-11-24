#include <ctype.h>
#include <getopt.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <netdb.h>
#include <sys/types.h>
#include <netinet/in.h>
#include <sys/socket.h>
#include <time.h>
#define AGENTSTRING "NTRIP NtripLinuxClient"
#define MAXDATASIZE 1000
#define ALARMTIME   (2*60)
#define MaxSatNum		33
#define NumOfBytesInMes	5
#define TRUE			1
#define	ZCOUNT_SCALE	0.6
#define	RANGE_SMALL		0.02
#define	RANGE_LARGE		0.32
#define	RANGERATE_SMALL	0.002
#define	RANGERATE_LARGE	0.032
#define XYZ_SCALE		0.01
#define DXYZ_SCALE		0.1
#define	LA_SCALE		90.0/32767.0
#define	LO_SCALE		180.0/32767.0
#define	FREQ_SCALE		0.1
#define	FREQ_OFFSET		190.0
#define CNR_OFFSET		24
#define TU_SCALE		5
#define MSG_FULLCOR	 1
#define MSG_REFPARM	 3
#define MSG_DATUM	 4
#define MSG_CONHLTH	 5
#define MSG_NULL	 6
#define MSG_BEACALM	 7
#define MSG_SUBSCOR	 9
#define MSG_SPECIAL	16
#define	PARITY_25	0xec7cd200
#define	PARITY_26	0x763e6900
#define	PARITY_27	0xbb1f3400
#define	PARITY_28	0x5d8f9a00
#define	PARITY_29	0xaec7cd00
#define	PARITY_30	0x2dea2700
#define XOR		^
#define AND		&
#define	BIT_MASK			0x01
#define	BYTE_MASK			0xff
#define LSB_BYTE_MASK		0x03
#define MSB_BYTE_MASK		0xfc
#define LSB_THRI_MASK		0x07
#define LSB_FIVE_MASK		0x1f
#define LSB_SIX_MASK		0x3f
#define LSB_TEN_MASK		0x3ff
#define LSB_THERTIN_MASK	0x1fff
#define DATA_SHIFT		 6
#define FILL_BASE		24

#ifndef COMPILEDATE
	#define COMPILEDATE " built " __DATE__
#endif

static char revisionstr[] = "$Revision: 1.27 $";
static char datestr[]     = "$Date: 2007/05/16 14:16:21 $";

struct Args {
  const char *server;
  int         port;
  const char *user;
  const char *password;
  const char *nmea;
  const char *data;
  int         bitrate;
};

unsigned char reverse_bits[] = {
0,32,16,48,8,40,24,56,4,36,20,52,12,44,28,60,
2,34,18,50,10,42,26,58,6,38,22,54,14,46,30,62,
1,33,17,49,9,41,25,57,5,37,21,53,13,45,29,61,
3,35,19,51,11,43,27,59,7,39,23,55,15,47,31,63};

typedef struct {
	double	PRc;
	double	RRc;
	unsigned char	IOD;
} Corrections_;

typedef struct{
	unsigned	PRn				:  5;
	unsigned	UDRE			:  2;
	unsigned	Scale			:  1;
	signed		PRc				: 16;
	signed		RRc				:  8;
	unsigned	IOD				:  8;
} _MessageType1;

typedef union IntToByte_{
	unsigned int	Word;
	unsigned char	Byte[4];
} IntToByte_;

typedef union _BytesToMessageType1{
	unsigned char	Byte[NumOfBytesInMes];
	_MessageType1	MessageType1;
} _BytesToMessageType1;

int bitparity[6];
int bitword[24];
int bool_cslip = 0;
int bool_dprc = 0;
int bool_drrc = 0;
int bool_factor = 0;
int bool_freq = 0;
int bool_gnsstime = 0;
int bool_gpsglo = 0;
int bool_health = 0;
int bool_iod = 0;
int bool_length = 0;
int bool_mpe = 0;
int bool_msg = 0;
int bool_mtype = 0;
int bool_multi = 0;
int bool_pcind = 0;
int bool_phase = 0;
int bool_pr = 0;
int bool_prc = 0;
int bool_pre = 0;
int bool_prn = 0;
int bool_qual = 0;
int bool_refid = 0;
int bool_rrc = 0;
int bool_seqno = 0;
int bool_smooth = 0;
int bool_udre = 0;
int bool_xyz = 0;
int bool_zcount = 0;

void printBits(size_t const size, void const * const ptr);
void printBits2(size_t const size, void const * const ptr);
void printBits3(size_t const size, void const * const ptr);
void printstringasbinary(char* s);
static const char *getWord(size_t const size, void const * const ptr, int start);

#ifdef NO_LONG_OPTS
	#define LONG_OPT(a)
#else
	#define LONG_OPT(a) a
	static struct option opts[] = {
	{ "bitrate",    no_argument,       0, 'b'},
	{ "data",       required_argument, 0, 'd'},
	{ "server",     required_argument, 0, 's'},
	{ "password",   required_argument, 0, 'p'},
	{ "port",       required_argument, 0, 'r'},
	{ "user",       required_argument, 0, 'u'},
	{ "nmea",       required_argument, 0, 'n'},
	{ "help",       no_argument,       0, 'h'},
	{0,0,0,0}};
#endif
#define ARGOPT "-d:bhp:r:s:u:n:"

#ifdef __GNUC__
	static __attribute__ ((noreturn)) void sighandler_alarm(
	int sig __attribute__((__unused__)))
#else
	static void sighandler_alarm(int sig)
#endif
{
  fprintf(stderr, "ERROR: more than %d seconds no activity\n", ALARMTIME);
  exit(1);
}

static const char *geturl(const char *url, struct Args *args) {
  static char buf[1000];
  static char *Buffer = buf;
  static char *Bufend = buf+sizeof(buf);

  if(strncmp("ntrip:", url, 6)) return "URL must start with 'ntrip:'.";
  url += 6;
  if(*url != '@' && *url != '/') {
    args->data = Buffer;
    while(*url && *url != '@' &&  *url != ';' &&*url != '/' && Buffer != Bufend) *(Buffer++) = *(url++);
    if(Buffer == args->data) return "Mountpoint required.";
    else if(Buffer >= Bufend-1) return "Parsing buffer too short.";
    *(Buffer++) = 0;
  }
  if(*url == '/') {
    ++url;
    args->user = Buffer;
    while(*url && *url != '@' && *url != ';' && *url != ':' && Buffer != Bufend) *(Buffer++) = *(url++);
    if(Buffer == args->user) return "Username cannot be empty.";
    else if(Buffer >= Bufend-1) return "Parsing buffer too short.";
    *(Buffer++) = 0;
    if(*url == ':') ++url;
    args->password = Buffer;
    while(*url && *url != '@' && *url != ';' && Buffer != Bufend) *(Buffer++) = *(url++);
    if(Buffer == args->password) return "Password cannot be empty.";
    else if(Buffer >= Bufend-1) return "Parsing buffer too short.";
    *(Buffer++) = 0;
  }
  if(*url == '@') {
    ++url;
    args->server = Buffer;
    while(*url && *url != ':' && *url != ';' && Buffer != Bufend) *(Buffer++) = *(url++);
    if(Buffer == args->server) return "Servername cannot be empty.";
    else if(Buffer >= Bufend-1) return "Parsing buffer too short.";
    *(Buffer++) = 0;
    if(*url == ':') {
      char *s2 = 0;
      args->port = strtol(++url, &s2, 10);
      if((*s2 && *s2 != ';') || args->port <= 0 || args->port > 0xFFFF) return "Illegal port number.";
      url = s2;
    }
  }
  if(*url == ';') {
    args->nmea = ++url;
    while(*url) ++url;
  }
  return *url ? "Garbage at end of server string." : 0;
}

static int getargs(int argc, char **argv, struct Args *args) {
	int res = 1;
	int getoptr;
	char *a;
	int i = 0, help = 0;
	char *t;

	args->server = "www.euref-ip.net";
	args->port = 2101;
	args->user = "";
	args->password = "";
	args->nmea = 0;
	args->data = 0;
	args->bitrate = 0;
	help = 0;

	do {
		#ifdef NO_LONG_OPTS
		    switch((getoptr = getopt(argc, argv, ARGOPT)))
		#else
		    switch((getoptr = getopt_long(argc, argv, ARGOPT, opts, 0)))
		#endif
	    {
			case 's': args->server = optarg; break;
			case 'u': args->user = optarg; break;
			case 'p': args->password = optarg; break;
			case 'd': args->data = optarg; break;
			case 'n': args->nmea = optarg; break;
			case 'b': args->bitrate = 1; break;
			case 'h': help=1; break;
			case 1:
			{
				const char *err;
				if((err = geturl(optarg, args))) {
					fprintf(stderr, "%s\n\n", err);
					res = 0;
				}
			}
			break;
			case 'r':
				args->port = strtoul(optarg, &t, 10);
				if((t && *t) || args->port < 1 || args->port > 65535) res = 0;
			break;
			case -1: break;
		}
	} while(getoptr != -1 && res);

	for(a = revisionstr+11; *a && *a != ' '; ++a)
	revisionstr[i++] = *a;
	revisionstr[i] = 0;
	datestr[0] = datestr[7];
	datestr[1] = datestr[8];
	datestr[2] = datestr[9];
	datestr[3] = datestr[10];
	datestr[5] = datestr[12];
	datestr[6] = datestr[13];
	datestr[8] = datestr[15];
	datestr[9] = datestr[16];
	datestr[4] = datestr[7] = '-';
	datestr[10] = 0;

	if(!res || help) {
		fprintf(stderr, "Version %s (%s) GPL" COMPILEDATE "\nUsage:\n%s -s server -u user ...\n"
		" -d " LONG_OPT("--data     ") "the requested data set\n"
		" -s " LONG_OPT("--server   ") "the server name or address\n"
		" -p " LONG_OPT("--password ") "the login password\n"
		" -r " LONG_OPT("--port     ") "the server port number (default 2101)\n"
		" -u " LONG_OPT("--user     ") "the user name\n"
		" -n " LONG_OPT("--nmea     ") "NMEA string for sending to server\n"
		" -b " LONG_OPT("--bitrate  ") "output bitrate\n"
		"or using an URL:\n%s ntrip:mountpoint[/username[:password]][@server[:port]][;nmea]\n"
		, revisionstr, datestr, argv[0], argv[0]);
		exit(1);
	}
	return res;
}

static const char encodingTable [64] = {
  'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P',
  'Q','R','S','T','U','V','W','X','Y','Z','a','b','c','d','e','f',
  'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
  'w','x','y','z','0','1','2','3','4','5','6','7','8','9','+','/'
};

static int encode(char *buf, int size, const char *user, const char *pwd) {
	unsigned char inbuf[3];
	char *out = buf;
	int i, sep = 0, fill = 0, bytes = 0;
	while(*user || *pwd) {
		i = 0;
		while(i < 3 && *user) inbuf[i++] = *(user++);
		if(i < 3 && !sep)    {inbuf[i++] = ':'; ++sep; }
		while(i < 3 && *pwd)  inbuf[i++] = *(pwd++);
		while(i < 3)         {inbuf[i++] = 0; ++fill; }
		if(out-buf < size-1) *(out++) = encodingTable[(inbuf [0] & 0xFC) >> 2];
		if(out-buf < size-1) *(out++) = encodingTable[((inbuf [0] & 0x03) << 4) | ((inbuf [1] & 0xF0) >> 4)];
		if(out-buf < size-1) {
			if(fill == 2) *(out++) = '=';
			else *(out++) = encodingTable[((inbuf [1] & 0x0F) << 2) | ((inbuf [2] & 0xC0) >> 6)];
		}
		if(out-buf < size-1) {
			if(fill >= 1) *(out++) = '=';
			else *(out++) = encodingTable[inbuf [2] & 0x3F];
		}
		bytes += 4;
	}
	if(out-buf < size) *out = 0;
	return bytes;
}

int main(int argc, char **argv) {
	struct Args args;
	setbuf(stdout, 0);
	setbuf(stdin, 0);
	setbuf(stderr, 0);
	signal(SIGALRM,sighandler_alarm);
	alarm(ALARMTIME);
	if(getargs(argc, argv, &args)) {
		int i, sockfd, numbytes;
		unsigned char buf[MAXDATASIZE];
		struct hostent *he;
		struct sockaddr_in their_addr;
		if(!(he=gethostbyname(args.server))) {
			fprintf(stderr, "Server name lookup failed for '%s'.\n", args.server);
			exit(1);
		}
		if((sockfd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
			perror("socket");
			exit(1);
		}
		their_addr.sin_family = AF_INET;
		their_addr.sin_port = htons(args.port);
		their_addr.sin_addr = *((struct in_addr *)he->h_addr);
		memset(&(their_addr.sin_zero), '\0', 8);
		if(connect(sockfd, (struct sockaddr *)&their_addr, sizeof(struct sockaddr)) == -1) {
			perror("connect");
			exit(1);
		}
		if(!args.data) {
			i = snprintf(buf, MAXDATASIZE,
				"GET / HTTP/1.1\r\n"
				"User-Agent: %s/%s\r\n"
				#ifdef UNUSED
					"Accept: */*\r\n"
					"Connection: close\r\n"
				#endif
				"\r\n"
				, AGENTSTRING, revisionstr);
		} else {
			i=snprintf(buf, MAXDATASIZE-40,
				"GET /%s HTTP/1.1\r\n"
				"User-Agent: %s/%s\r\n"
				#ifdef UNUSED
					"Accept: */*\r\n"
					"Connection: close\r\n"
				#endif
				"Authorization: Basic "
				, args.data, AGENTSTRING, revisionstr);
			if(i > MAXDATASIZE-40 || i < 0) {
				fprintf(stderr, "Requested data too long\n");
				exit(1);
			}
			i += encode(buf+i, MAXDATASIZE-i-4, args.user, args.password);
			if(i > MAXDATASIZE-4) {
				fprintf(stderr, "Username and/or password too long\n");
				exit(1);
			}
			buf[i++] = '\r';
			buf[i++] = '\n';
			buf[i++] = '\r';
			buf[i++] = '\n';
			if(args.nmea) {
				int j = snprintf(buf+i, MAXDATASIZE-i, "%s\r\n", args.nmea);
				if(j >= 0 && i < MAXDATASIZE-i) i += j;
				else {
					fprintf(stderr, "NMEA string too long\n");
					exit(1);
				}
			}
		}
		if(send(sockfd, buf, (size_t)i, 0) != i) {
			perror("send");
			exit(1);
		}
		if(args.data) {
			int k = 0;
			int starttime = time(0);
			int lastout = starttime;
			int totalbytes = 0;
			while((numbytes=recv(sockfd, buf, MAXDATASIZE-1, 0)) != -1) {
				alarm(ALARMTIME);
				if(!k) {
					if(numbytes < 12 || strncmp("ICY 200 OK\r\n", buf, 12)) {
						fprintf(stderr, "Could not get the requested data: ");
						for(k = 0; k < numbytes && buf[k] != '\n' && buf[k] != '\r'; ++k) fprintf(stderr, "%c", isprint(buf[k]) ? buf[k] : '.');
						fprintf(stderr, "\n");
						exit(1);
					}
					++k;
				} else {
					totalbytes += numbytes;
					if(totalbytes < 0) {
						totalbytes = 0;
						starttime = time(0);
						lastout = starttime;
					}
					printf(".");
					printBits3(sizeof(buf), buf);
					if(args.bitrate) {
						int t = time(0);
						if(t > lastout + 60) {
							lastout = t;
							fprintf(stderr, "Bitrate is %dbyte/s (%d seconds accumulated).\n", totalbytes/(t-starttime), t-starttime);
						}
					}
				}
			}
		} else {
			while((numbytes=recv(sockfd, buf, MAXDATASIZE-1, 0)) > 0) printf("recv = %d bytes\n", numbytes);
		}
		close(sockfd);
	}
	return 0;
}

#define BYTE_TO_BINARY(byte)  \
	(byte & 0x80 ? '1' : '0'), \
	(byte & 0x40 ? '1' : '0'), \
	(byte & 0x20 ? '1' : '0'), \
	(byte & 0x10 ? '1' : '0'), \
	(byte & 0x08 ? '1' : '0'), \
	(byte & 0x04 ? '1' : '0'), \
	(byte & 0x02 ? '1' : '0'), \
	(byte & 0x01 ? '1' : '0') ;

const char *byte_to_binary(int x) {
	static char b[9];
	b[0] = '\0';
	int z;
	for (z = 128; z > 0; z >>= 1) strcat(b, ((x & z) == z) ? "1" : "0");
	return b;
}

char *sstrncat(char *dest, const char *src, size_t n) {
	size_t dest_len = strlen(dest);
	size_t i;
	for (i = 0 ; i < n && src[i] != '\0' ; i++) dest[dest_len + i] = src[i];
	dest[dest_len + i] = '\0';
	return dest;
}

char *subString(char* str, int start, int length) {
	char *newString = (char *)malloc(length * sizeof(char));
	int i, x = 0;
	int end=start+length;
	for(i = start ; i < end; i++) newString[x++] = str[i];
	newString[x] = '\0';
	return newString;
}

int fromBinary(char *s) {
	return (int) strtol(s, NULL, 2);
}

int bitpoint(char bt, int bp){
	return (bt >> bp) & 0x01;
}

void loadword(char ch1, char ch2, char ch3, char ch4, char ch5) {
	int cnt;
}

unsigned char SumArray( unsigned char *Array, unsigned char Length) {
	unsigned char i = 0, SUM = 0;
	for( i = 0 ; i < Length ; i++ ) SUM += Array[i];
	return SUM;
}

unsigned char ParityCheck( unsigned int Word, unsigned char *D29Star, unsigned char *D30Star) {
	char	i, j, SUM = 0;
	unsigned char	ComputedParity[6] = {0}, ReceivedParity[6] = {0}, Diff[6] = {0};
	unsigned int	ActiveBits[6] = {0};
	ActiveBits[0] = Word AND PARITY_25;
	ActiveBits[1] = Word AND PARITY_26;
	ActiveBits[2] = Word AND PARITY_27;
	ActiveBits[3] = Word AND PARITY_28;
	ActiveBits[4] = Word AND PARITY_29;
	ActiveBits[5] = Word AND PARITY_30;
	ComputedParity[0] ^= *D29Star;
	ComputedParity[1] ^= *D30Star;
	ComputedParity[2] ^= *D29Star;
	ComputedParity[3] ^= *D30Star;
	ComputedParity[4] ^= *D30Star;
	ComputedParity[5] ^= *D29Star;
	for( i = 23 ; i >= 0 ; i-- )
		for ( j = 0 ; j < 6 ; j++ ) ComputedParity[j] ^= (unsigned char)( (ActiveBits[j] >> (i + 8)) AND BIT_MASK );
	for( j = 0 ; j < 6 ; j++ ){
		ReceivedParity[j] = (unsigned char) ( (Word >> ( 7 - j )) AND BIT_MASK );
		Diff[j] = ReceivedParity[j] XOR ComputedParity[j];
	}
	*D29Star = ReceivedParity[4];
	*D30Star = ReceivedParity[5];

	SUM = SumArray( Diff , 6);
	if ( SUM == 0 )	return	TRUE;
	return 0;
}

unsigned int BuildWord(unsigned char *Package , unsigned char D30Star) {
	unsigned char	i, SwapByte = 0, LeftBitsShift = 0;
	unsigned int	Word = 0;
	for( i = 0 ; i < 5 ; i++) {
		SwapByte = reverse_bits[ Package[i] AND LSB_SIX_MASK ];
		SwapByte = (unsigned char) (SwapByte << 2);
		SwapByte = SwapByte AND MSB_BYTE_MASK;
		if ( ( D30Star == 1 ) && ( i != 4 ) ) SwapByte = SwapByte XOR MSB_BYTE_MASK;
		LeftBitsShift = (4 - i) * 6;
		Word = Word XOR SwapByte << LeftBitsShift;
	}
	return Word;
}

void CopyIntToByteArray(char Length , unsigned char *Dest, unsigned int *Source,unsigned char i, unsigned char StartIndex) {
	IntToByte_	IntToByte;
	IntToByte.Word = *Source;
	do {
		Dest[ StartIndex++  ] = IntToByte.Byte[ 3 - i ];
		i++;
	} while ( Length-- );
}

void readmessage1(unsigned char MesLen ,unsigned char *RawBuf ,unsigned char *D29s ,unsigned char *D30s) {
	Corrections_	Corrections[MaxSatNum] = {0};
	_BytesToMessageType1	BytesToMessageType1;
	unsigned char		SatID = 0, IOD = 0, ScaleFactor = 0, UDRE = 0, i, MesBytesIndex = 0, BytesRead = 0, WordsLeft = 0, WordCounter = 0, Validity = 0, BytesToCopy = 0;
	signed short 		PRc = 0;
	char		RRc = 0;
	double		ScaleFactorPRc, ScaleFactorRRc;
	unsigned int		Word[31] = {0};
	memset(BytesToMessageType1.Byte, 0 , sizeof(BytesToMessageType1) );
	for ( i = 0 ; i < MesLen ; i++){
		Word[i]  = BuildWord( RawBuf + i*5, *D30s );
		Validity = ParityCheck( Word[i], D29s , D30s ); // D29s and D30s are pointers
		WordsLeft++;
		while ( (WordsLeft * 3 - BytesRead) >= (NumOfBytesInMes - MesBytesIndex) ){
			if ( BytesRead == 0 && (NumOfBytesInMes - MesBytesIndex) >= 3) BytesToCopy = 3;
			else if ( BytesRead > 0 && (NumOfBytesInMes - MesBytesIndex + BytesRead) >= 3 ) BytesToCopy = 3 - BytesRead;
			else if ( (NumOfBytesInMes - MesBytesIndex + BytesRead) <= 3 ) BytesToCopy = NumOfBytesInMes - MesBytesIndex;
			else break;
			CopyIntToByteArray( BytesToCopy - 1 , BytesToMessageType1.Byte , &Word[i - WordsLeft + 1],BytesRead , MesBytesIndex);
			BytesRead		=	(BytesRead + BytesToCopy) % 3;
			MesBytesIndex	+=  BytesToCopy;
			if (BytesRead == 0)	WordsLeft--;
			if (MesBytesIndex == 5){
				BytesToMessageType1.Byte[1] ^= BytesToMessageType1.Byte[2];
				BytesToMessageType1.Byte[2] ^= BytesToMessageType1.Byte[1];
				BytesToMessageType1.Byte[1] ^= BytesToMessageType1.Byte[2];
				MesBytesIndex	= 0;
				if (BytesToMessageType1.MessageType1.Scale == 0){
					ScaleFactorPRc = RANGE_SMALL;
					ScaleFactorRRc = RANGERATE_SMALL;
				} else {
					ScaleFactorPRc = RANGE_LARGE;
					ScaleFactorRRc = RANGERATE_LARGE;
				}
				SatID = (BytesToMessageType1.MessageType1.PRn == 0 ) ? 32 : BytesToMessageType1.MessageType1.PRn;
				Corrections[SatID].PRc = BytesToMessageType1.MessageType1.PRc * ScaleFactorPRc;
				Corrections[SatID].RRc = BytesToMessageType1.MessageType1.RRc * ScaleFactorRRc;
				Corrections[SatID].IOD = BytesToMessageType1.MessageType1.IOD;
				memset(BytesToMessageType1.Byte , 0 , sizeof(BytesToMessageType1));
			}
		}
	}
	printf("\n");
	for ( i = 1; i < 33 ; i++ )printf("SID = %4d, IOD = %5d, PRC = %7.3f, RRC = %7.3f\n",i,Corrections[i].IOD,Corrections[i].PRc,Corrections[i].RRc);
	printf("\n");
}

void readmessage(unsigned char MessageType, unsigned char Length , unsigned char *Message , unsigned char *D29s , unsigned char *D30s) {
	switch (MessageType){
		case 1: readmessage1(Length , Message , D29s , D30s); break;
	}
}

void readheader(unsigned char *MessageType, unsigned char *Length, unsigned char *Header ,unsigned char *D29s , unsigned char *D30s) {
	unsigned char	Preamble = 0 , SequenceNum = 0 , Health = 0 , Validity = 0;
	unsigned short	StationID = 0, Mod_Z_count = 0;
	unsigned int	Word = 0, Word_[2] = {0};
	Word		= BuildWord( Header , *D30s );
	Validity	= ParityCheck( Word, D29s , D30s );
	Preamble		= (unsigned char )  ((Word >> 24) AND BYTE_MASK);
	*MessageType	= (unsigned char )  ((Word >> 18) AND LSB_SIX_MASK);
	StationID		= (unsigned short) ((Word >> 8)  AND LSB_TEN_MASK);
	Word		= BuildWord( Header + 5 , *D30s );
	Validity	= ParityCheck( Word, D29s , D30s );
	Mod_Z_count	=	(unsigned short) ((Word >> 19 ) AND LSB_THERTIN_MASK);
	SequenceNum	=	(unsigned char )  ((Word >> 16 ) AND LSB_THRI_MASK);
	*Length		=	(unsigned char )  ((Word >> 11 ) AND LSB_FIVE_MASK);
	Health		=	(unsigned char )  ((Word >> 8 )  AND LSB_THRI_MASK);
	if (*MessageType == 0) *MessageType = 64;
	if  (Preamble == 102 && *MessageType == 1) {
		printf("\n-------------\n");
		printf("messagetype=%d\n",*MessageType);
		printf("-------------\n");
		printf("seqno=%d\n", SequenceNum);
		printf("Preamble(102)=%d ",Preamble);
		printf("StationID=%d ",StationID);
		printf("Mod Z-count=%6.1f ",Mod_Z_count * 0.6);
		printf("length=%2.0f ",*Length);
		printf("health=%d\n",Health);
	}
}

void printBits3(size_t const size, void const * const ptr) {
	unsigned char *b = (unsigned char*) ptr;
	int i, strindi;
	unsigned char RawBuf[255] = {0}, d29star = 0, d30star = 0, D29s, D30s;
	unsigned char MessageType = 0, Length = 0;
	char d;
	strindi = 0;
	d29star = 0;
	d30star = 0;
	D29s = d29star;
	D30s = d30star;
	while (strindi < size) {
		memset(&RawBuf, 0, 255);
		MessageType	= 0;
		Length		= 0;
		for (i = 0 ; i < 10 ; i++) {
			RawBuf[i] = b[strindi+i];
		}
		RawBuf[i] = '\0';
		strindi = strindi + 10;
		readheader(&MessageType , &Length , RawBuf , &D29s , &D30s);
		d = 0;
		while ((d == 0) && (i < ( 5 * Length + 10) ) ) {
			if (b[strindi] == 0x0D){
				strindi++;
			} else {
				RawBuf[i] = b[strindi];
				strindi++;
				i++;
			}
		}
		RawBuf[i] = '\0';
		readmessage(MessageType, Length, RawBuf + 10 ,&D29s , &D30s);
	}
}

static const char *getWord(size_t const size, void const * const ptr, int start) {
	unsigned char *b = (unsigned char*) ptr;
	unsigned char *t;
	static char res[256];
	int i, j, k;
	strcpy(res, "");
	k = 0;
	for (i = 0; i < 5; i++) {
		t = (unsigned char*) byte_to_binary(b[start+i]);
		for (j=7; j>= 2; j--) res[k++] = t[j];
		res[k] = '\0';
	}
	return res;
}

void printBits2(size_t const size, void const * const ptr) {
	unsigned char *b = (unsigned char*) ptr;
	char res[4096];
	int i, j, k, strindi, messagetype;
	size_t len;
	strindi = 0;
	messagetype = 0;
	while(1) {
		strcpy(res, "");
		strcpy(res, getWord(sizeof(b), b, strindi));
		if (strcmp(subString(res, 0, 8), "01100110") == 0) {
			messagetype = fromBinary(subString(res, 8, 6));
			if (messagetype == 1) {
				printf("\n[\n");
				printf("RTCM message type : %d\n", fromBinary(subString(res, 8, 6)));
				printf("Reference Station ID : %d, %s\n", fromBinary(subString(res, 14, 10)), subString(res, 14, 10));
				strcpy(res, "");
				strindi = strindi + 5;
				strcpy(res, getWord(sizeof(b), b, strindi));
				printf("Modfied Z-count(0-6000) : %d, %s\n", fromBinary(subString(res, 0, 12)), subString(res, 0, 12));
				printf("Length og Message SEQ(0-7) : %d, %s\n", fromBinary(subString(res, 12, 4)), subString(res, 12, 4));
				printf("Number of Sequence N(0-31) : %d, %s\n", fromBinary(subString(res, 16, 5)), subString(res, 16, 5));
				printf("State of working(0-7) : %d, %s\n", fromBinary(subString(res, 21, 3)), subString(res, 21, 3));
				printf("]\n");
				strcpy(res, "");
				strindi = strindi + 5;
				strcpy(res, getWord(sizeof(b), b, strindi));
				printf("scale factor : %d, %s\n", fromBinary(subString(res, 0, 1)), subString(res, 0, 1));
				printf("UDRE : %d, %s\n", fromBinary(subString(res, 1, 2)), subString(res, 1, 2));
				printf("satellite ID : %d, %s\n", fromBinary(subString(res, 3, 5)), subString(res, 3, 5));
				printf("PRC : %d, %s\n", fromBinary(subString(res, 9, 15)), subString(res, 9, 15));
				strcpy(res, "");
				strindi = strindi + 5;
				strcpy(res, getWord(sizeof(b), b, strindi));
				printf("RRC %d, %s\n", fromBinary(subString(res, 0, 8)), subString(res, 0, 8));
				printf("IOD %d, %s\n", fromBinary(subString(res, 8, 8)), subString(res, 8, 8));
				printf("----------------------------------------------\n");
			} else {
				printf("RTCM message type : %d, %s\n", fromBinary(subString(res, 8, 6)), subString(res, 8, 6));
				printf("----------------------------------------------\n");
				strindi = strindi + 5;
				strindi = strindi + 5;
			}
		} else {
			strindi++;
		}
		if (strindi >= sizeof(b)) break;
	}
}

void printBits(size_t const size, void const * const ptr) {
	unsigned char *b = (unsigned char*) ptr;
	unsigned char byte;
	int i, j;
	for (i=size-1;i>=0;i--) {
		for (j=7;j>=0;j--) {
			byte = (b[i] >> j) & 1;
			printf("%u", byte);
		}
		printf(" ");
	}
	puts("");
}
