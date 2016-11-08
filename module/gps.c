#include <string.h>
#include <ctype.h>
#include<hwconfig.h>
#include <SoftwareSerial.h>
#include "TinyGPS.h"


SoftwareSerial ss(8,9);

void gps_setup()
{
  ss.begin(9600);
  Serial.print("Simple TinyGPS library v. ");
}

void gps_data()
{
  bool newData = false;
  unsigned long chars;
  unsigned short sentences, failed;

  // For one second we parse GPS data and report some key values
  for (unsigned long start = millis(); millis() - start < 1000;)
  {
    while (ss.available())
    {
      char c = ss.read();
      // Serial.write(c); // uncomment this line if you want to see the GPS data flowing
      if (gps.encode(c)) // Did a new valid sentence come in?
        newData = true;
    }
  }

  if (newData)
  {
    
    float flat, flon;
    int fdate;
    unsigned long age;
    byte fmonth,fday,fhour,fmin,fsec,fhundred;
    gps.f_get_position(&flat, &flon, &age);
   /* Serial.print("LAT=");
    Serial.print(flat == TinyGPS::GPS_INVALID_F_ANGLE ? 0.0 : flat, 6);
    
    Serial.print(" LON=");
    Serial.print(flon == TinyGPS::GPS_INVALID_F_ANGLE ? 0.0 : flon, 6);
  
    Serial.print("\n\r");
    Serial.print(flat);*/
  }
  
  gps.stats(&chars, &sentences, &failed);
 /* Serial.print(" CHARS=");
  Serial.print(chars);
  Serial.print(" SENTENCES=");
  Serial.print(sentences);
  Serial.print(" CSUM ERR=");
  Serial.println(failed);
  */
  
}

int get_year()
{
  byte fmonth,fday,fhour,fmin,fsec,fhundred;
  int fdate;
  gps.crack_datetime(&fdate,&fmonth,&fday,&fhour,&fmin,&fsec,&fhundred);
  //Serial.print("YEAR=");
 // Serial.print(fdate == TinyGPS::GPS_INVALID_FIX_TIME ? 0.0 : fdate, 6);
  return(fdate);  
}

int get_month()
{
  byte fm,fday,fhour,fmin,fsec,fhundred;
  int fdate;
  
  gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
 // Serial.print("month=");
  //Serial.print(fm == TinyGPS::GPS_INVALID_FIX_TIME ? 0.0 : fdate, 6);
  return(fm); 

}

int get_day()
{
  byte fm,fday,fhour,fmin,fsec,fhundred;
  int fdate;
  
  gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
 // Serial.print("month=");
  //Serial.print(fday== TinyGPS::GPS_INVALID_FIX_TIME ? 0.0 : fdate, 6);
  return(fday); 

}

int get_hour()
{
  byte fm,fday,fhour,fmin,fsec,fhundred;
  int fdate;
  
  gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
 // Serial.print("month=");
  //Serial.print(fhour== TinyGPS::GPS_INVALID_FIX_TIME ? 0.0 : fdate, 6);
  return(fhour); 

}

int get_min()
{
  byte fm,fday,fhour,fmin,fsec,fhundred;
  int fdate;
  
  gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
 // Serial.print("month=");
  //Serial.print(fday== TinyGPS::GPS_INVALID_FIX_TIME ? 0.0 : fdate, 6);
  return(fmin); 

}
int get_sec()
{
  byte fm,fday,fhour,fmin,fsec,fhundred;
  int fdate;
  
  gps.crack_datetime(&fdate,&fm,&fday,&fhour,&fmin,&fsec,&fhundred);
 // Serial.print("month=");
  //Serial.print(fday== TinyGPS::GPS_INVALID_FIX_TIME ? 0.0 : fdate, 6);
  return(fsec); 

}

float get_latitude()
{
  float flat,flon;
  unsigned long age;
   gps.f_get_position(&flat, &flon, &age);
  //  Serial.print("LAT=");
  //  Serial.print(flat == TinyGPS::GPS_INVALID_F_ANGLE ? 0.0 : flat, 6);
  return(flat);
  
  
}

float get_longitude()
{
  float flat,flon;
  unsigned long age;
   gps.f_get_position(&flat, &flon, &age);
   // Serial.print("LAT=");
    //Serial.print(flon == TinyGPS::GPS_INVALID_F_ANGLE ? 0.0 : flat, 6);
  return(flon);
  
  
}