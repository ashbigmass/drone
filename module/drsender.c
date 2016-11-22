/*



gcc timer.c drsender.c -03 -lm -o drsender 


   $GPGGA - Global Positioning System Fix Data
   $GPGSA - GPS DOP and active satellites 
   $GPGSV - GPS Satellites in view
   $GPRMC - Recommended minimum specific GPS/Transit data
   $GPVTG - Track made good and ground speed
   
*/

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

struct Args
{
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
 
/* Function prototypes*/
void tokenize(char *sentence, char *oneline, FILE *fileName);
float convertFromNmeaSentenceToDecimalCoord(float coordinates, const char *val);
float convertFromKnotsToKmHour(float knots);
void timer_handler(void);

int var=0;

int main(int argc, char **argv) {
	struct Args args;
  
	if(start_timer(1000, &timer_handler))
	{
		printf("\n timer error\n");
		return(1);
	}

	printf("\npress ctl-c to quit.\n");

	while(1) {
		if(var > 1) {
			break;
		}
	}

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
		/*Print header*/
		fprintf(out, "%s\n","data = [" );
 
		/*Read file line by line*/
		while (fscanf (in, "%79[^\n]\n", line) == 1) { 
			// if GPRMC found filter it
			// GPGGA Global Positioning System Fix Data
			// GPRMC Recommended Minimmum data
			if (strstr(line, "$GPRMC")) {
				// Get tokens from strings
				tokenize("$GPRMC", line, out);
				fprintf(out, "%s\n","," );
			}
		}
		/* Print footer*/
		fprintf(out, "%s\n","]" );
		/* Close file*/
		fclose(out);
	} else {
		perror(fileSource);
	}	
}

/*tokenize() reads each line and takes tokens when divider found (comma)*/
void tokenize(char *sentence, char *oneline, FILE *fileName) {
    
	const char delimiter[2] = DELIMITER;
	char *token;
	int counter = 0;
	
	/* get the first token */
	token = strtok(oneline, delimiter);
	
	float ltemp;
	/* walk through other tokens */
	while( token != NULL ) { 
		/*convert the 1st token time*/
		if(counter == 1){
			ltemp = atof(token); 
			
			fprintf(fileName,"%s", "{sentence: ");
			fprintf(fileName,"%s, ", sentence );

			fprintf(fileName,"%s", "time: ");
			fprintf(fileName,"%f, ", ltemp );
		}
		
		/*convert the 4th token latitude*/
		if(counter == 3){
			ltemp = atof(token); 
			ltemp = convertFromNmeaSentenceToDecimalCoord(ltemp,"m"); //"m" for meridian
		} 
	 
		/*If 5th token == South multiply by -1*/
		if (counter == 4){
			if (*token == 'S'){
				ltemp *= ( -1 );
			}
			fprintf(fileName,"%s", "latitude: ");
			fprintf(fileName,"%f, ", ltemp );
		} 
	  
		/*convert the 6th token longitude*/
		if(counter == 5){
			ltemp = atof(token); 
			ltemp = convertFromNmeaSentenceToDecimalCoord(ltemp, "p"); //"p" for parallel
		}
	  
		/*If 6th token == East multiply by -1*/
		if (counter == 6){/*convert the 4th token latitude*/
			if (*token == 'W') {
				ltemp *= ( -1 );
			}
			fprintf(fileName, "%s", "longitude: " );
			fprintf(fileName,"%f, ", ltemp );
		}
		
		// Speed over ground, knots
		if (counter == 7 ){
			ltemp = atof(token);
			ltemp = convertFromKnotsToKmHour(ltemp);
			fprintf(fileName, "speed: %0.1f, ", ltemp);
		}
		
		//Track Angle in degree true
		if(counter == 8) {
			ltemp = atof(token); 
			fprintf(fileName,"%s", "head: ");
			fprintf(fileName,"%f} ", ltemp );
		}
 
		token = strtok(NULL, delimiter);
		++counter;
	}
}

/*selfexplantory*/
float convertFromNmeaSentenceToDecimalCoord(float coordinates, const char *val) {
	/* Sample from gps 5153.6605*/
    /* Check limits*/
    if ((*val == 'm') && (coordinates < 0.0 && coordinates > MAX_LATITUDE)) {
		return 0;     
    }
    if (*val == 'p' && (coordinates < 0.0 && coordinates > MAX_LONGITUDE)) {
		return 0;
    }
	
	int b;//to store the degrees
	float c; //to store de decimal
 
   /*Calculate the value in format nn.nnnnnn*/
   /*Explanations at: http://www.mapwindow.org/phorum/read.php?3,16271,16310*/
 
	b = coordinates/100; // 51 degrees
	c= (coordinates/100 - b)*100 ; //(51.536605 - 51)* 100 = 53.6605
	c /= 60; // 53.6605 / 60 = 0.8943417
	c += b; // 0.8943417 + 51 = 51.8943417
	
	return c;
}

/* Selfexplanatory*/
float convertFromKnotsToKmHour(float knots) {
	knots = knots * KNOTS_TO_KM;
	
	return knots;
}
