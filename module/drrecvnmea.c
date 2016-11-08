/*


gcc drrecvnmea.c -o drrecvnmea -I/usr/include/mysql -L/usr/lib64/mysql -lmysqlclient -lm

   $GPGGA - Global Positioning System Fix Data
   $GPGSA - GPS DOP and active satellites 
   $GPGSV - GPS Satellites in view
   $GPRMC - Recommended minimum specific GPS/Transit data
   $GPVTG - Track made good and ground speed
   
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <arpa/inet.h>
#include <sys/types.h>
#include <sys/socket.h>

#include "mysql.h"

// Mysql Define
#define BUFLEN		512
#define DB_HOST 	"127.0.0.1"
#define DB_USER 	"dr"
#define DB_PASS 	"dr"
#define DB_NAME 	"dr"
#define DB_PORT		12345
#define CHOP(x) x[strlen(x) - 1] = ' '
// UDP Define
#define PORT 9876

int main(void) {
	MYSQL *connection=NULL, conn;
	MYSQL_RES *sql_result;
	MYSQL_ROW sql_row;
	
	int		i, fields;
	int		sock;
	int		client_addr_size;
	struct	sockaddr_in   server_addr;
	struct	sockaddr_in   client_addr;
	char	buff_rcv[BUFLEN+5];

	sock = socket(PF_INET, SOCK_DGRAM, IPPROTO_UDP);
	if (sock == -1) {
		printf("Server : socket create error\n");
		exit(1);
	}

	memset(&server_addr, 0, sizeof(server_addr));
	server_addr.sin_family = AF_INET;
	server_addr.sin_port = htons(PORT);
	server_addr.sin_addr.s_addr = htonl(INADDR_ANY);

	if (-1 == bind(sock, (struct sockaddr*)&server_addr, sizeof(server_addr))) {
		printf("Server : Can't bind local address\n");
		exit(1);
	}

	while (1) {
		memset(buff_rcv, 0x0, BUFLEN);
		client_addr_size = sizeof(client_addr);
		recvfrom(sock, buff_rcv, BUFLEN, 0, ( struct sockaddr*)&client_addr, &client_addr_size);
		printf("Received packet from %s:%d\n", inet_ntoa(client_addr.sin_addr), ntohs(client_addr.sin_port));
        printf("Data: %s\n" , buff_rcv);
         		
		
		printf("receive UDP : %sn", buff_rcv);
/*      
		mysql_init(&conn);
		connection = mysql_real_connect(&conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, (char *)NULL, 0);
		if (connection == NULL) {
			fprintf(stderr, "Mysql connection error : %s", mysql_error(&conn));
		}

		mysql_query(&conn,"select * from TestDB");
		sql_result = mysql_store_result(&conn);  // 모든 출력 결과를 서버에서 한번에 다 받아옴(데이터 많을때는 자제하라)

		fields = mysql_num_fields(sql_result); // 필드 갯수 구함

		while(sql_row = mysql_fetch_row(sql_result)) // 한 행을 구함
		{                                             
			for (i = 0; i < fields ; i++)  // 모든 필드 값을 출력
			{
				printf("%s",sql_row[i]);
			}
		}
		mysql_free_result(sql_result);
		mysql_close(&conn);
*/
	}
	
	close(sock);
    return 0;
}
