# Lightning Login
Let people log into your wordpress website using their bitcoin wallet. Integrate the Lightning Login plugin to generate a secure login qr code that anyone can use to create an account on your website or, if they have already made one, log in.

# Installation
Install and activate the plugin through the plugins page in the backend of your wordpress installation. Just select Add new and upload the zip file. Be aware that this plugin requires that your server have the php-gmp extension installed. If the plugin does not work for you, install that php extension on the server and try again. On a debian-based server such as ubuntu or a raspberry pi, this can be accomplished via this command: sudo apt install php-gmp -y

# Usage
Add the following shortcode to any page on your site.

`[generatelnurl]`

When a user visits the page, the shortcode will display as a clickable lightning login qr code. Users who scan the qr code with a bitcoin wallet that supports the lnurl-auth protocol will automatically get a new account with a random username and secure password or, if they’ve signed in with that bitcoin wallet before, they will be signed into their existing user (without ever needing to remember — or even see — their password!). After logging in, the user will be redirected to a page that you specify in settings.
