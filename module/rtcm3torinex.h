#ifndef RTCM3TORINEX_H
#define RTCM3TORINEX_H
#define PRN_GPS_START				1
#define PRN_GPS_END				32
#define PRN_GLONASS_START		38
#define PRN_GLONASS_END		61
#define PRN_WAAS_START			120
#define PRN_WAAS_END				138
#define GNSSENTRY_C1DATA		0
#define GNSSENTRY_C2DATA		1
#define GNSSENTRY_P1DATA		2
#define GNSSENTRY_P2DATA		3
#define GNSSENTRY_L1CDATA		4
#define GNSSENTRY_L1PDATA		5
#define GNSSENTRY_L2CDATA		6
#define GNSSENTRY_L2PDATA		7
#define GNSSENTRY_D1CDATA	8
#define GNSSENTRY_D1PDATA		9
#define GNSSENTRY_D2CDATA	10
#define GNSSENTRY_D2PDATA		11
#define GNSSENTRY_S1CDATA		12
#define GNSSENTRY_S1PDATA		13
#define GNSSENTRY_S2CDATA		14
#define GNSSENTRY_S2PDATA		15
#define GNSSENTRY_NUMBER		16
#define GNSSDF_C1DATA			(1<<GNSSENTRY_C1DATA)
#define GNSSDF_C2DATA			(1<<GNSSENTRY_C2DATA)
#define GNSSDF_P1DATA				(1<<GNSSENTRY_P1DATA)
#define GNSSDF_P2DATA				(1<<GNSSENTRY_P2DATA)
#define GNSSDF_L1CDATA			(1<<GNSSENTRY_L1CDATA)
#define GNSSDF_L1PDATA			(1<<GNSSENTRY_L1PDATA)
#define GNSSDF_L2CDATA			(1<<GNSSENTRY_L2CDATA)
#define GNSSDF_L2PDATA			(1<<GNSSENTRY_L2PDATA)
#define GNSSDF_D1CDATA			(1<<GNSSENTRY_D1CDATA)
#define GNSSDF_D1PDATA			(1<<GNSSENTRY_D1PDATA)
#define GNSSDF_D2CDATA			(1<<GNSSENTRY_D2CDATA)
#define GNSSDF_D2PDATA			(1<<GNSSENTRY_D2PDATA)
#define GNSSDF_S1CDATA			(1<<GNSSENTRY_S1CDATA)
#define GNSSDF_S1PDATA			(1<<GNSSENTRY_S1PDATA)
#define GNSSDF_S2CDATA			(1<<GNSSENTRY_S2CDATA)
#define GNSSDF_S2PDATA			(1<<GNSSENTRY_S2PDATA)
#define RINEXENTRY_C1DATA		0
#define RINEXENTRY_C2DATA		1
#define RINEXENTRY_P1DATA		2
#define RINEXENTRY_P2DATA		3
#define RINEXENTRY_L1DATA		4
#define RINEXENTRY_L2DATA		5
#define RINEXENTRY_D1DATA		6
#define RINEXENTRY_D2DATA		7
#define RINEXENTRY_S1DATA		8
#define RINEXENTRY_S2DATA		9
#define RINEXENTRY_NUMBER		10
#define LIGHTSPEED					2.99792458e8
#define GPS_FREQU_L1				1575420000.0
#define GPS_FREQU_L2				1227600000.0
#define GPS_WAVELENGTH_L1	(LIGHTSPEED / GPS_FREQU_L1)
#define GPS_WAVELENGTH_L2	(LIGHTSPEED / GPS_FREQU_L2)
#define GLO_FREQU_L1_BASE	1602000000.0
#define GLO_FREQU_L2_BASE	1246000000.0
#define GLO_FREQU_L1_STEP		562500.0
#define GLO_FREQU_L2_STEP		437500.0
#define GLO_FREQU_L1(a)			(GLO_FREQU_L1_BASE+(a)*GLO_FREQU_L1_STEP)
#define GLO_FREQU_L2(a)			(GLO_FREQU_L2_BASE+(a)*GLO_FREQU_L2_STEP)
#define GLO_WAVELENGTH_L1(a)	(LIGHTSPEED / GLO_FREQU_L1(a))
#define GLO_WAVELENGTH_L2(a)	(LIGHTSPEED / GLO_FREQU_L2(a))
#define GNSSDF_LOCKLOSSL1	(1<<29)
#define GNSSDF_LOCKLOSSL2	(1<<30)
#define GPSEPHF_L2PCODEDATA		(1<<0)
#define GPSEPHF_L2PCODE				(1<<1)
#define GPSEPHF_L2CACODE				(1<<2)
#define GPSEPHF_VALIDATED				(1<<3)
#define R2R_PI		  3.1415926535898
#define GLOEPHF_UNHEALTHY	   (1<<0)
#define GLOEPHF_ALMANACHEALTHOK (1<<1)
#define GLOEPHF_ALMANACHEALTHY  (1<<2)
#define GLOEPHF_PAVAILABLE	  (1<<3)
#define GLOEPHF_P10TRUE		 (1<<4)
#define GLOEPHF_P11TRUE		 (1<<5)
#define GLOEPHF_P2TRUE		  (1<<6)
#define GLOEPHF_P3TRUE		  (1<<7)

struct gnssdata {
	int	flags;
	int	week;
	int	numsats;
	double	timeofweek;
	double	measdata[24][GNSSENTRY_NUMBER];
	int	dataflags[24];
	int	satellites[24];
	int	snrL1[24];
	int	snrL2[24];
};

struct gpsephemeris {
	int	flags;
	int	satellite;
	int	IODE;
	int	URAindex;
	int	SVhealth;
	int	GPSweek;
	int	IODC;
	int	TOW;
	int	TOC;
	int	TOE;
	double clock_bias;
	double clock_drift;
	double clock_driftrate;
	double Crs;
	double Delta_n;
	double M0;
	double Cuc;
	double e;
	double Cus;
	double sqrt_A;
	double Cic;
	double OMEGA0;
	double Cis;
	double i0;
	double Crc;
	double omega;
	double OMEGADOT;
	double IDOT;
	double TGD;
};

struct glonassephemeris {
	int	GPSWeek;
	int	GPSTOW;
	int	flags;
	int	almanac_number;
	int	frequency_number;
	int	tb;
	int	tk;
	int	E;
	double tau;
	double gamma;
	double x_pos;
	double x_velocity;
	double x_acceleration;
	double y_pos;
	double y_velocity;
	double y_acceleration;
	double z_pos;
	double z_velocity;
	double z_acceleration;
};

struct RTCM3ParserData {
	unsigned char Message[2048];
	int	MessageSize;
	int	NeedBytes;
	int	SkipBytes;
	int	GPSWeek;
	int	GPSTOW;
	struct gnssdata Data;
	struct gpsephemeris ephemerisGPS;
	struct glonassephemeris ephemerisGLONASS;
	struct gnssdata DataNew;
	int	size;
	int	lastlockl1[64];
	int	lastlockl2[64];
	#ifdef NO_RTCM3_MAIN
	double antX;
	double antY;
	double antZ;
	double antH;
	char   antenna[256+1];
	int	blocktype;
	#endif
	int	datapos[RINEXENTRY_NUMBER];
	int	dataflag[RINEXENTRY_NUMBER];
	int	dataposGPS[RINEXENTRY_NUMBER];
	int	dataflagGPS[RINEXENTRY_NUMBER];
	int	dataposGLO[RINEXENTRY_NUMBER];
	int	dataflagGLO[RINEXENTRY_NUMBER];
	int	numdatatypesGPS;
	int	numdatatypesGLO;
	int	validwarning;
	int	init;
	int	startflags;
	int	rinex3;
	const char * headerfile;
	const char * glonassephemeris;
	const char * gpsephemeris;
	FILE *glonassfile;
	FILE *gpsfile;
};

#ifndef PRINTFARG
#ifdef __GNUC__
#define PRINTFARG(a,b) __attribute__ ((format(printf, a, b)))
#else
#define PRINTFARG(a,b)
#endif
#endif

void HandleHeader(struct RTCM3ParserData *Parser);
int RTCM3Parser(struct RTCM3ParserData *handle);
void HandleByte(struct RTCM3ParserData *Parser, unsigned int byte);
void PRINTFARG(1,2) RTCM3Error(const char *fmt, ...);
void PRINTFARG(1,2) RTCM3Text(const char *fmt, ...);

#endif
