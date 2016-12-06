#include <ctype.h>
#include <getopt.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <sys/types.h>
#include <netinet/in.h>
#include <sys/socket.h>
#include <time.h>
#include "timer.h"

struct Args {
  const char *server;
  int         port;
  const char *user;
  const char *password;
  const char *nmea;
  const char *data;
  int         bitrate;
};
#define MAX_LONGITUDE	180
#define MAX_LATITUDE	90
#define KNOTS_TO_KM		1.852
#define DELIMITER		","

void tokenize(char *sentence, char *oneline, FILE *fileName);
float convertFromNmeaSentenceToDecimalCoord(float coordinates, const char *val);
float convertFromKnotsToKmHour(float knots);
void timer_handler(void);

int var=0;

int main(int argc, char **argv) {
	struct Args args;
	if(start_timer(1000, &timer_handler)) {
		printf("\n timer error\n");
		return(1);
	}
	printf("\npress ctl-c to quit.\n");
	while(1) if(var > 1) break;
	stop_timer();
	return(0);
}

void timer_handler(void) {
	char line[80];
	static const char fileSource[] = "gps.txt";
	static const char fileOutput[] = "output.json";
 	printf("timer: var is %i\n", var++);
	FILE *in = fopen(fileSource, "r");
	FILE *out = fopen(fileOutput, "w");
	if (in != NULL) {
		fprintf(out, "%s\n","data = [" );
		while (fscanf (in, "%79[^\n]\n", line) == 1) {
			if (strstr(line, "$GPRMC")) {
				tokenize("$GPRMC", line, out);
				fprintf(out, "%s\n","," );
			}
		}
		fprintf(out, "%s\n","]" );
		fclose(out);
	} else {
		perror(fileSource);
	}
}

void tokenize(char *sentence, char *oneline, FILE *fileName) {
	const char delimiter[2] = DELIMITER;
	char *token;
	int counter = 0;
	token = strtok(oneline, delimiter);
	float ltemp;
	while( token != NULL ) {
		if(counter == 1){
			ltemp = atof(token);
			fprintf(fileName,"%s", "{sentence: ");
			fprintf(fileName,"%s, ", sentence );
			fprintf(fileName,"%s", "time: ");
			fprintf(fileName,"%f, ", ltemp );
		}
		if(counter == 3){
			ltemp = atof(token);
			ltemp = convertFromNmeaSentenceToDecimalCoord(ltemp,"m");
		}
		if (counter == 4){
			if (*token == 'S') ltemp *= ( -1 );
			fprintf(fileName,"%s", "latitude: ");
			fprintf(fileName,"%f, ", ltemp );
		}
		if(counter == 5){
			ltemp = atof(token);
			ltemp = convertFromNmeaSentenceToDecimalCoord(ltemp, "p");
		}
		if (counter == 6){
			if (*token == 'W') ltemp *= ( -1 );
			fprintf(fileName, "%s", "longitude: " );
			fprintf(fileName,"%f, ", ltemp );
		}
		if (counter == 7 ){
			ltemp = atof(token);
			ltemp = convertFromKnotsToKmHour(ltemp);
			fprintf(fileName, "speed: %0.1f, ", ltemp);
		}
		if(counter == 8) {
			ltemp = atof(token);
			fprintf(fileName,"%s", "head: ");
			fprintf(fileName,"%f} ", ltemp );
		}
		token = strtok(NULL, delimiter);
		++counter;
	}
}

float convertFromNmeaSentenceToDecimalCoord(float coordinates, const char *val) {
    if ((*val == 'm') && (coordinates < 0.0 && coordinates > MAX_LATITUDE)) return 0;
    if (*val == 'p' && (coordinates < 0.0 && coordinates > MAX_LONGITUDE)) return 0;
	int b;
	float c;
	b = coordinates/100;
	c= (coordinates/100 - b)*100 ;
	c /= 60;
	c += b;
	return c;
}

float convertFromKnotsToKmHour(float knots) {
	knots = knots * KNOTS_TO_KM;
	return knots;
}
