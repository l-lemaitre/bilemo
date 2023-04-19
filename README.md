# bilemo (Create a web service exposing an API)

Project 7 of the formation [Application Developer - PHP/Symfony of OpenClassrooms](https://openclassrooms.com/fr/paths/500-developpeur-dapplication-php-symfony).

Instructions for installing the project :<br>
- Pull the project with the command git clone and this repository URL (https://github.com/l-lemaitre/bilemo) to the root of your working directory<br>
- Run the command => composer update<br>
- Creation of JWT keys<br>
Run the commands :<br>
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096<br>
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout<br>
You will be asked for a “passphrase”. This passphrase will serve as a key for the encoding/decoding of the token, it must remain secret.
- Install the mysql "bilemo" database on your web server<br>
- File to modify to establish a connection with the "bilemo" database and copy the JWT_PASSPHRASE :<br>
.env or .env.local

The documentation is accessible at the URL: https://127.0.0.1:8000/api/doc.