import os

from dotenv import load_dotenv
from mysql.connector import pooling


load_dotenv()


mysql_pool = pooling.MySQLConnectionPool(
    pool_name="projectflow_pool",
    pool_size=5,
    host=os.getenv("MYSQL_HOST"),
    port=int(os.getenv("MYSQL_PORT", "3306")),
    database=os.getenv("MYSQL_DATABASE"),
    user=os.getenv("MYSQL_USER"),
    password=os.getenv("MYSQL_PASSWORD"),
)


def get_mysql_connection():
    return mysql_pool.get_connection()
