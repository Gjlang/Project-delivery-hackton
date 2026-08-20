import os

from dotenv import load_dotenv
from mysql.connector import pooling


load_dotenv()


# On Cloud Run, MySQL is reached through a Unix socket (Cloud SQL's own
# connector), not a TCP host/port -- set MYSQL_SOCKET_PATH there and this
# switches automatically. Locally (Docker Compose, bare venv) that variable
# stays unset and the pool connects over TCP exactly as before.
_socket_path = os.getenv("MYSQL_SOCKET_PATH")

mysql_pool = pooling.MySQLConnectionPool(
    pool_name="projectflow_pool",
    pool_size=5,
    unix_socket=_socket_path,
    host=None if _socket_path else os.getenv("MYSQL_HOST"),
    port=int(os.getenv("MYSQL_PORT", "3306")),
    database=os.getenv("MYSQL_DATABASE"),
    user=os.getenv("MYSQL_USER"),
    password=os.getenv("MYSQL_PASSWORD"),
)


def get_mysql_connection():
    return mysql_pool.get_connection()
