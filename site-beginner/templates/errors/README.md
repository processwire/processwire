When a fatal error occurs, ProcessWire displays the message:

"Unable to complete this request due to an error."

The message is intentionally vague for security purposes. 
Details will be logged to /site/assets/logs/errors.txt.

When present in this directory, the file 500.html will be 
displayed instead of the generic error message above. Feel 
free to modify this file to show whatever you would like.
Please note the following:

* 500.html is plain HTML and has no PHP or API access.

* You may enter the tag {message} and ProcessWire will
  replace this with additional details when applicable.
  When not applicable, it will make it blank.

* If you are logged in as an admin, ProcessWire will 
  give you a detailed error message rather than 500.html. 

