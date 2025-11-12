#!/bin/bash
# leaderboard-system/scripts/load_test.sh

echo "Starting load testing..."
echo "Ensure ApacheBench (ab) or k6 is installed."

# Example using ApacheBench (ab) for simple load testing
# You might need to install it: `sudo apt-get install apache2-utils` on Debian/Ubuntu
# Or `brew install httpd` on macOS

AB_BIN=$(which ab)
if [ -z "$AB_BIN" ]; then
    echo "ApacheBench (ab) not found. Please install it (e.g., sudo apt-get install apache2-utils)."
    exit 1
fi

API_BASE_URL="http://localhost/api/v1"

echo "Testing /api/v1/scores (POST - Write operations)"
# Simulate 100 requests with 10 concurrent users
for i in {1..5}; do
    PLAYER_ID=$(( RANDOM % 1000 + 1 )) # Random player ID
    GAME_ID=$(( RANDOM % 5 + 1 ))     # Random game ID
    SCORE=$(( RANDOM % 10000 + 100 )) # Random score

    echo "Running write test for player ${PLAYER_ID}, game ${GAME_ID}, score ${SCORE}"
    # Using curl for POST as ab for POST with JSON is complex. For actual load, use k6.
    # ab -n 100 -c 10 -p ./scripts/post_score.json -T application/json ${API_BASE_URL}/scores # If using ab with a static JSON body
    curl -s -X POST -H "Content-Type: application/json" \
         -d "{\"player_id\":${PLAYER_ID},\"game_id\":${GAME_ID},\"score\":${SCORE}}" \
         ${API_BASE_URL}/scores > /dev/null
done
echo "Basic write test complete (using curl for simplicity). Consider k6 for proper load testing."


echo ""
echo "Testing /api/v1/leaderboards (GET - Read operations)"
# Simulate 200 requests with 20 concurrent users for leaderboard reads
GAME_ID_READ=$(( RANDOM % 5 + 1 ))
echo "Running read test for game ${GAME_ID_READ} leaderboard"
${AB_BIN} -n 200 -c 20 "${API_BASE_URL}/leaderboards?game_id=${GAME_ID_READ}&limit=100"

echo ""
echo "Testing /api/v1/leaderboards/{gameId}/player/{playerId} (GET - Player Rank)"
PLAYER_ID_RANK=$(( RANDOM % 1000 + 1 ))
GAME_ID_RANK=$(( RANDOM % 5 + 1 ))
echo "Running player rank test for player ${PLAYER_ID_RANK} in game ${GAME_ID_RANK}"
${AB_BIN} -n 50 -c 5 "${API_BASE_URL}/leaderboards/${GAME_ID_RANK}/player/${PLAYER_ID_RANK}"


echo ""
echo "Load testing complete. For detailed metrics and advanced scenarios, consider tools like k6."

# Example k6 script placeholder (requires k6 installed)
: '
# k6 run scripts/k6_load_test.js
// scripts/k6_load_test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  stages: [
    { duration: '30s', target: 50 }, // simulate ramp-up of traffic from 1 to 50 users over 30 seconds.
    { duration: '1m', target: 50 },  // stay at 50 users for 1 minute
    { duration: '30s', target: 0 },  // ramp-down to 0 users
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
  },
};

export default function () {
  const BASE_URL = 'http://localhost/api/v1';

  // Simulate score submission
  let playerId = Math.floor(Math.random() * 1000) + 1;
  let gameId = Math.floor(Math.random() * 5) + 1;
  let score = Math.floor(Math.random() * 10000) + 100;

  let headers = { 'Content-Type': 'application/json' };
  let payload = JSON.stringify({
    player_id: playerId,
    game_id: gameId,
    score: score,
  });

  let res = http.post(`${BASE_URL}/scores`, payload, { headers: headers });
  check(res, { 'status is 201': (r) => r.status === 201 });

  // Simulate leaderboard read
  res = http.get(`${BASE_URL}/leaderboards?game_id=${gameId}&limit=100`);
  check(res, { 'status is 200': (r) => r.status === 200 });

  // Simulate player rank read
  res = http.get(`${BASE_URL}/leaderboards/${gameId}/player/${playerId}`);
  check(res, { 'status is 200': (r) => r.status === 200 });

  sleep(0.5); // wait for 0.5 seconds between iterations
}
'