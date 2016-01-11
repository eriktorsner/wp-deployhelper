# wp-deployhelper

Simple command line utility to assist deployments via ftp. Intended for the specific condition where a remote server is accessible via ftp and http. Typically, this is the case when deploying to shared host like Bluehost or GoDaddy standard web accounts.

wp-deployhelper was originally written as a support package for [WP Bootstrap](https://github.com/eriktorsner/wp-bootstrap), a deployment tool for WordPress, hence the name. 

## Concept
wp-deployhelper uses a combination of ftp and http to transfer compressed sets of changed files to a remote server. By keeping track of both local and remote state, the number of files transferred can be kept to a minimum speed kept to a maximum. Typical time to transfer a standard (empty) WordPress install to Bluehost is around 30s on the first transfer and 5-8s for incremental updates.

wp-deployhelper also comes with a rewrite feature that allows individual files to be rewritten using regular expressions after unpack. Typically used to change passwords or database host in configuration files.

## Installation

To add this package as a local, per-project dependency to your project, simply add a dependency on `eriktorsner/wp-deployhelper` to your project's `composer.json` file. Here is a minimal example of a `composer.json` file that just defines a dependency wp-bootstrap:

    {
        "require": {
            "eriktorsner/wp-deployhelper": "0.1.*"
        }
    }
 
 Then

    $ composer update


## Configuration

wp-deployhelper uses a single json configuration file that needs to be in the project root folder named ftpsettings.json.

    {
        "host": "ftp.mydomain.com",
        "user": "mydomain",
        "pass": "secret",
        "remotePath": "/public_html",
        "localPath": "/vagrant/www/wordpress-default",
        "httpUrl": "www.mydomain.com",
        "rewrite": [{
            "file": "/wp-config.php",
            "pattern": "/define\\('DB_HOST', '.+'\\);/i",
            "replace": "define('DB_HOST', 'localhost');"
        }]
    }

| Name | Description |
|---------|-----------|
|host| ftp host name|
|user| ftp user name|
|pass| ftp password|
|remotePath| path on the remote server|
|localPath| path on the local server|
|httpUrl| http url that corresponds to the remote path|
|rewrite| rerwrite rules, see below|

**Notes:** host, user and pass are used to connect to the remote server using the standard php ftp client. Currently no support for alternative ports etc.

remotePath and httpUrl needs to match. A php script will be uploaded to the remote path and is expected to be accessible using the httpUrl, if these don't match, wp-deployhelper will not work at all.

**Rewrite rules**
The rewrite rules are an array of objects, each representing a rule. On files that matches the *file* name/glob pattern a [preg_replace](http://php.net/manual/en/function.preg-replace.php)  will be made. If the preg_replace results in any hits, the file is saved with its new content. 

 - **file:** a file name or glob pattern checked using php [fnmatch](http://php.net/manual/en/function.fnmatch.php). As each file is unpacked, it's name is checked against *file* and if it matches, the rewrite pattern is applied
 - **pattern** Passed in as the pattern argument to preg_replace
 - **replace** Passed in as the replace argument to preg_replace

## Running
wp-deplyhelper only has one command with no arguments:

    $ vendor/bin/wp-deployhelper deploy

## Saved state

After each run local and remote state is saved in the subfolder wp-deployhelper directly under the project root. There's currently no way to alter the state folder path or name.

## Rules

The rules for what files to transfer or delete are currently not configurable. The intended use case is that the local server controls what files that are supposed to exist on the server. However, files that are added "runtime" on the server will not be removed.

| Local | Remote | Result | Reasoning |
|---------|-----------|-----------|-----------|
| NEW, MOD| * | Copied to remote | Any new or modified local file will be transferred regardless of remote state|
| EXISTS| DEL, MOD | Copied to remote | A remotely modified or deleted file will be replaced by the original from local |
| DEL | * | Deleted from remote| Deleted local file will delete the file remotely |
| * | NEW | No action | Files added remotely are assumed to be correctly added by the application itself |

**Note:** A locally renamed file will be treated as two files. One deleted and one new.

## Detailed description
**Step 1.** wp-deployhelper works by creating a complete (recursive) list of all local and remote files. To get the remote index, a php a script is pushed to the target server to take advantage of the php *scandir* fundtion rather than relying on indexing via ftp (slow).  The two indexes as first compared against saved state for both the local and remote sides to figure out which files that needs to be transferred or deleted on the remote server.

**Step 2.** In the next step, wp-deployhelper creates a zip archive with all the needed files as well as some meta information. The zip archive is then sent to the target server via ftp (to avoid potential file upload limitations). The php scrip is then called to unpack the archive, delete files that should be deleted etc.

**Step 3.** As the last step, wp-deployhelper takes a new index of the remote state and saves both the local and remote states. 

On the local side, the indexing process uses md5 file hashes to detect modified files. But for performance reasons, the remote side just hashes modification and file size. Theoretically, it's possible to do changes on remote files that goes undetected, but in reality that should be rare.


## Security

Currently wp-deployhelper is rather insecure since it relies on plain ftp for the file transfers. To increase security, wp-deployhelper uses a (pseudo) random file name for the script and zip file. Each time the tool runs, a new random name is created. As soon as the process is finished both the zip and php files are removed from the target server.

		
## Version history

**0.1.0** 
  - First version. Support for remote deploys and rewrite
  - Tested on Bluehost, Loopia (SWE)
  