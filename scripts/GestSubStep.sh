#! /bin/bash
redis-cli -h 93.45.8.218 -p 6379 publish $1 "{\"visitId\":\"$2\",\"enSubs\":$3,\"itSubs\":$4,\"visitType\":\"$5\",\"command\":\"$6\",\"target\":$7}"
