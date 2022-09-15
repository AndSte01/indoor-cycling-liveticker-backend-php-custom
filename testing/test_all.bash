#!/bin/bash
# run all test scripts and write output to file

# setup
FORCE_COLOR=true hopp test setup.json -e env.json | tee results.ansi

# users
FORCE_COLOR=true hopp test users.json -e env.json | tee -a results.ansi

# clean
hopp test setup.json -e env.json > /dev/null

# competitions
FORCE_COLOR=true hopp test competitions.json -e env.json | tee -a results.ansi

# clean
hopp test setup.json -e env.json > /dev/null

# disciplines
FORCE_COLOR=true hopp test disciplines.json -e env.json | tee -a results.ansi

# clean
hopp test setup.json -e env.json > /dev/null

# results
FORCE_COLOR=true hopp test results.json -e env.json | tee -a results.ansi

# clean
hopp test setup.json -e env.json > /dev/null

# dummy data
hopp test dummy_data.json -e env.json > /dev/null

# poll
FORCE_COLOR=true hopp test poll.json -e env.json | tee -a results.ansi

# clean
hopp test setup.json -e env.json > /dev/null