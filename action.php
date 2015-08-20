<?php
/**
 * DokuWiki Plugin autopage (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Vseроvolod Lutovinov <klopp@yandex.ru>
 * 
 * This plugin is evolution of autostartpage plugin:
 *  http://dokuwiki.org/plugin:autostartpage 
 */

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
        $controller->register_hook( 'TPL_CONTENT_DISPLAY', 'BEFORE', $this, 
                'autopage_parse_template' );
    }

    function auto_replace_internals($m)
    {
        if( $m[1] == 'LANG' )
        {
            $txt = $this->getLang( $m[2] );
            return $txt ? $txt : '?' . $m[2] . '?';
        }
        elseif( $m[1] == 'AUTOID' )
        {
            $autoid = p_get_metadata( 'plugin_autopage', 'autoid' );
            return $autoid ? $autoid : '0';
        }
        return '?';
    }

    private function _auto_parse_template($tpl)
    {
        if( strstr( $tpl, '@AUTO_' ) === false ) return $tpl;
        $tpl = preg_replace_callback( '/@AUTO_([A-Z]+?)_(.+?)@/', 
                array($this,'auto_replace_internals' 
                ), $tpl );
        $tpl = preg_replace_callback( '/@AUTO_([A-Z]+?)@/', 
                array($this,'auto_replace_internals' 
                ), $tpl );
        return $tpl;
    }

    function autopage_parse_template(&$e, $param)
    {
        $e->data = $this->_auto_parse_template( $e->data );
    }

    private function autopage_get_template($name, $id, $language)
    {
        global $conf;
        
        $wikitext = false;
        
        if( !$language )
        {
            if( $conf['lang'] )
            {
                $wikitext = $this->autopage_get_template( $name, $id, 
                        '-' . $conf['lang'] );
                if( $wikitext ) return $wikitext;
            }
        }
        
        $autocreate = $this->getConf( $name );
        if( $language ) $autocreate .= $language;
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
        $wikitext = parsePageTemplate( $data );
        return $wikitext;
    }

    function autopage_create_page(&$e, $param)
    {
        global $ID;
        global $ACT;
        global $INPUT;
        
        if( $ACT != 'edit' ) return;
        if( !is_object( $INPUT ) || !$INPUT->get || !$INPUT->get->param( 
                'autot' ) ) return;
        if( @file_exists( wikiFN( $ID ) ) ) return;
        if( auth_quickaclcheck( $ID ) < AUTH_CREATE )
        {
            msg( $this->getLang( 'auto_no_access' ), -1 );
            return;
        }
        
        $wikitext = $this->autopage_get_template( 'auto_template_page', $ID );
        if( !$wikitext ) return;
        
        $t = $INPUT->get->str( 'autot' );
        $wikitext = str_replace( '@TITLE@', $t, $wikitext );
        saveWikiText( $ID, $wikitext, 'Created by AutoPage Plugin' );
        $autoid = p_get_metadata( 'plugin_autopage', 'autoid' );
        $autoid++;
        p_set_metadata( 'plugin_autopage', 
                array('autoid' => $autoid 
                ) );
        
        send_redirect( wl( $ID ) );
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function autopage_create_ns(Doku_Event &$event, $param)
    {
        $ns = $event->data[0];
        $ns_type = $event->data[1];
        
        if( $ns_type !== 'pages' ) return;
        if( auth_quickaclcheck( $ns ) < AUTH_CREATE ) return;
        if( @file_exists( wikiFN( $ns ) ) ) return;
        
        $wikitext = $this->autopage_get_template( 'auto_template_ns', $ns );
        if( $wikitext ) saveWikiText( $ns, $wikitext, 
                'Created by AutoPage Plugin' );
    }
}

