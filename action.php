<?php
/**
 * DokuWiki Plugin autopage (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Charles Knight <charles@rabidaudio.com>
 */

// must be run within Dokuwiki
if( !defined( 'DOKU_INC' ) ) die();

class action_plugin_autopage extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler &$controller)
    {
        $controller->register_hook( 'IO_NAMESPACE_CREATED', 'AFTER', $this, 
                'autopage_create_ns' );
        $controller->register_hook( 'ACTION_ACT_PREPROCESS', 'BEFORE', $this, 
                'autopage_create_page' );
    }

    private function plog($s)
    {
        $f = fopen( "/tmp/autopage.log", "a" );
        if( $f )
        {
            fwrite( $f, "$s\n" );
            fclose( $f );
        }
    }

    private function autopage_get_template($name, $id)
    {
        $autocreate = $this->getConf( $name );
        $wikitext = false;
        $ns = getNS( $id );
        
        $parts = explode( ':', $ns );
        while( count( $parts ) )
        {
            $templatefile = wikiFN( join( ':', $parts ) . ':' . $autocreate, 
                    '', false );
            if( @file_exists( $templatefile ) )
            {
                $wikitext = io_readFile( $templatefile );
                break;
            }
            array_pop( $parts );
        }
        if( !$wikitext )
        {
            $templatefile = wikiFN( $autocreate, '', false );
            if( @file_exists( $templatefile ) )
            {
                $wikitext = io_readFile( $templatefile );
            }
        }
        if( !$wikitext ) return false;
        
        $data = array('tpl' => $wikitext,'id' => $id 
        );
        return parsePageTemplate( $data );
    }

    function autopage_create_page(&$e, $param)
    {
        global $ID;
        global $ACT;
        global $INPUT;
        
        if( $ACT != 'edit' ) return;
        
        if( @file_exists( wikiFN( $ID ) ) ) return;
        
        if( !is_object( $INPUT ) || !$INPUT->get || !$INPUT->get->param( 
                'autot' ) ) return;
        
        if( auth_quickaclcheck( $ID ) < AUTH_CREATE ) return;
        
        $wikitext = $this->autopage_get_template( 'auto_template_page', $ID );
        if( !$wikitext ) return;
        
        $t = $INPUT->get->str( 'autot' );
        $wikitext = str_replace( '@TITLE@', $t, $wikitext );
        $wikitext = str_replace( '@PAGE@', $t, $wikitext );
        $wikitext = str_replace( '@!PAGE@', ucfirst( $t ), $wikitext );
        $wikitext = str_replace( '@!!PAGE@', ucwords( $t ), $wikitext );
        $wikitext = str_replace( '@!PAGE!@', strtoupper( $t ), $wikitext );
        
        saveWikiText( $ID, $wikitext, 'Created by AutoPage Plugin' );
        
        send_redirect( wl( $ID ) );
    }

    /**
     * [Custom event handler which performs action]
     *
     * @author Charles Knight, charles@rabidaudio.com
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function autopage_create_ns(Doku_Event &$event, $param)
    {
        global $conf;
        global $INFO;
        global $INPUT;
        
        $ns = $event->data[0];
        $ns_type = $event->data[1];
        
        if( $ns_type !== "pages" ) return;
        if( auth_quickaclcheck( $ns ) < AUTH_CREATE ) return;
        if( @file_exists( wikiFN( $ns ) ) ) return;
        
        $wikitext = $this->autopage_get_template( 'auto_template_ns', $ns );
        if( $wikitext ) saveWikiText( $ns, $wikitext, 
                'Created by AutoPage Plugin' );
    }
}

