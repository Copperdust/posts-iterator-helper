# posts-iterator-helper

Evals custom PHP code to every matching post from a WP_Query

## OPTIONS
`<code>`
Function *BODY* to exectute. Variable $post will be available. By default it will simply trigger update of the post.

`--<field>=<value>`
Allows passing of arguments to WP_Query, prepend every argument with --query_
Example: --query_posts_per_page=9 --query_paged=3

## EXAMPLES
    wp iterate-posts
    wp iterate-posts --query_posts_per_page=10 --query_paged=1
    wp iterate-posts --query_posts_per_page=-1 --query_post_status="Array('draft','publish')"
    wp iterate-posts $' print_r( $post ); '


## INSTALLATION
(I know this is ugly, I need to make this into a proper WP_CLI command, but alas, time escapes us all.)

1. Go to your WordPress's installation directory
2. Go to `./wp-content/mu-plugins`, if `mu-plugins` doesn't exist, create it
3. Clone this repo: `git clone git@github.com:Copperdust/posts-iterator-helper.git`
4. Actually add our files to WP's execution: 

   ```bash
   cat > posts-iterator-helper.php
   <?php
   include_once( WPMU_PLUGIN_DIR.'/posts-iterator-helper/posts-iterator-helper.php' );
   ```
