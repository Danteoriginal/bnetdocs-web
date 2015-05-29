<?php

# Configuration goes here
$config = array();

# Uncomment this line if you want to use Mysql, in format: array("host","login","password","database");
$config['mysql'] = array("localhost","todouser","todopassword", "bnetdocs_todo");

# To use old SQLite2 (instead of SQLite3) uncomment the line below
#$config['sqlite'] = 2;

# Language pack
$config['lang'] = "en";

# Specify password here to protect your tasks from modification,
#  or leave empty that everyone could read/write todolist
$config['password'] = "";

# Restrict access for the others when password above is set:
#  "no" - No access, "read" - Read only
$config['allow'] = "no";

# To disable smart syntax uncomment the line below
#$config['smartsyntax'] = 0;

# To disable auto detecting user time zone uncomment the line below
#$config['autotz'] = 0;

# To disable auto adding selected tag  comment out the line below or set value to 0
$config['autotag'] = 1;

# duedate calendar format: 1 => y-m-d (default), 2 => m/d/y, 3 => d.m.y
$config['duedateformat'] = 1;

# First day of week: 0-Sunday, 1-Monday, 2-Tuesday, .. 6-Saturday
$config['firstdayofweek'] = 1;

# select session handling mechanism: files or default (php default)
$config['session'] = 'files';

?>