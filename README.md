# Imperial Battles

An online multiplayer, turn-based tactics game. Played in a browser, connecting to a standard web server. Inspired by the Advance Wars series.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

1. Install XAMPP (https://www.apachefriends.org/index.html)
2. Create a new mysql database
3. Run the SQL queries in /sql on the new database
4. Fill out the privateInfo.ini file with the necessary information
5. Start the webserver (the easiest way is to go into the XAMPP install folder, then run xampp-control)

### Prerequisites

A webserver setup you are comfortable with. XAMPP is recommended as it was the development testing environment. Your webserver will need to support PHP and a relational database which is supported by PDOs (see http://php.net/manual/en/pdo.drivers.php).

## Deployment

Your privateInfo.ini file must be updated on the server to represent the new host address and database password. Make sure the db passwword is a good one! The .htaccess file included prevents access to this .ini file, but it is your job to ensure this is reflected in the server's actual access permissions.

The sql/ folder is just for setup purposes. It doesn't matter if this is public or not, but it doesn't need to be uploaded to the server.

All other files should go under your server's public access directory (e.g. htdocs, public_html, www, etcetera). The directory structure needs to be maintained as-is.

## Built With

* [jQuery](https://jquery.com/) - JavaScript web-dev standard library
* [gameQuery](http://gamequeryjs.com/) - JavaScript graphics engine
* [jQuery Cookie Plugin v1.3.1](https://github.com/carhartl/jquery-cookie) - jQuery plugin for reading, writing and deleting cookies

## Authors

* **Joe Saliba** - *Lead Developer* - [ThothRex](https://github.com/ThothRex)
* **James Doble** - *Artist* - [LinkedIn](https://www.linkedin.com/in/jamesdoble/)
* **Christos Karamanos** - *Website Co-Developer* - [LinkedIn](https://www.linkedin.com/in/christos-karamanos-60402881/)
* **Panos Evagorou** - *Website Co-Developer* - [LinkedIn](https://www.linkedin.com/in/panos-evagorou-73382186/)
* **Joseph Crowe** - *Game Graphics Developer* - [LinkedIn](https://www.linkedin.com/in/joseph-crowe-455a53bb/)

## License / Copyright

This project was initially created as a collaborative effort, and then later updated by me (ThothRex). I am not a lawyer, so my understanding of the resultant copyright is fuzzy.

It's a mess - don't try and sell this.

For the full details, see the [LICENSE.md](LICENSE.md) file.

## Acknowledgments

* Imperial College London - this work was initially created as a 2nd Year WebApps project.
