#include <string.h>
#include <ctype.h>
#include<hwconfig.h>
#include <SoftwareSerial.h>
#include "TinyGPS.h"

SoftwareSerial ss(8,9);

void gps_setup() {
	ss.begin(9600);
	Serial.print("Simple TinyGPS library v. ");
}

void gps_data() {
	bool newData = false;
	unsigned long chars;
	unsigned short sentences, failed;

	for (unsigned long start = millis(); millis() - start < 1000;) {
		while (ss.available()) { 
			char c = ss.read();
			if (gps.encode(c)) newData = true;
		}
	}

	if (newData) {
		float flat, flon;
		int fdate;
		unsigned long age;
		byte fmonth,fday,fhour,fmin,fsec,fhundred;
		gps.f_get_position(&flat, &flon, &age);
	}
	gps.stats(&chars, &sentences, &failed);
}

int get_year() {
	byte fmonth,fday,fhour,fmin,fsec,fhundred;
	int fdate;
	gps.crack_datetime(&fdate,&fmonth,&fday,&fhour,&fmin,&fsec,&fhundred);
	return(fdate);  
}

int get_month() {
	byte fm,fday,fhour,fmin,fsec,fhundred;
	int fdate;
	gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
	return(fm); 
}

int get_day() {
	byte fm,fday,fhour,fmin,fsec,fhundred;
	int fdate;
	gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
	return(fday); 
}

int get_hour() {
	byte fm,fday,fhour,fmin,fsec,fhundred;
	int fdate;
	gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
	return(fhour); 
}

int get_min() {
	byte fm,fday,fhour,fmin,fsec,fhundred;
	int fdate;
	gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
	return(fmin); 
}

int get_sec() {
	byte fm,fday,fhour,fmin,fsec,fhundred;
	int fdate;
	gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
	return(fsec); 
}

float get_latitude() {
	float flat,flon;
	unsigned long age;
	gps.f_get_position(&flat, &flon, &age);
	return(flat);
}

float get_longitude() {
	float flat,flon;
	unsigned long age;
	gps.f_get_position(&flat, &flon, &age);
	return(flon);
}