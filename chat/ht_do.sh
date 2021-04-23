cat *.pid
php ht_start.php restart -d 
echo "重启中。。。"
sleep 2
echo "重启成功"
cat *.pid
