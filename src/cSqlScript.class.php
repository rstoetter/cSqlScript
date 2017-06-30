<?php

// cSQLScript.class.php

namespace rstoetter\cSqlScript;

/**
  *
  * The class cSqlScript executes mysql sql scripts. The namespace is rstoetter\cSqlScript.
  * It recognizes line delimiters and emulates found BEGIN, COMMIT and ROLLBACK
  *
  * @author Rainer Stötter
  * @copyright 2010-2017 Rainer Stötter
  * @license MIT
  * @version =1.0
  *
  */

class cSqlScript extends cTextParser {

    /**
      *
      * @var mysqli the database connection
      *
      */

    protected $m_mysqli = false;

    /**
      *
      * @var string the following line
      *
      */

    protected $m_next_line = '';

    /**
      *
      * @var string the active delimiter, it defaults to ';'
      *
      */


    protected $m_actual_line_delimiter = ';';

    /**
      *
      * @var string the found delimiter set by a line containing a DELIMITER command
      *
      */

    protected $m_found_delimiter  = '';

    /**
      *
      * @var array tables found with CREATE TABLE commands
      *
      */


    public $m_table_names = array( );

    /**
      *
      * The constructor of cSqlScript objects
      *
      * Example:
      *
      *
      * @param string fname is the name of the script file to execute
      * @param bool $debug whether debug messages should be displayed or not. It defaults to false
      *
      */

    public function __construct( $fname, $debug = false ) {

	assert( is_string( $fname ) );
        assert( strlen( $fname ) );

        $this->cTextParser( $fname, $debug );

        /*
        $this->m_scriptfilename = $fname;
        $this->m_debug = $debug;

        if ( file_exists( $fname ) ) {
            $this->m_script = file_get_contents  ( $fname );
            $this->m_scriptlen = strlen( $this->m_script);

            // if ($this->m_debug) echo "<br>$this->m_script<br>";
        }

        */

    }   // function cSQLScript( )


    /**
      *
      * The method GetNextLine( ) reads the next line from the script file and stores the line in $m_next_line
      * Line delimiers are recognized
      *
      * Example:
      *
      * @return bool true, if no error occured ( no EOT found )
      *
      */

    protected function GetNextLine() {

        // echo "<br>GetNextLine() : ";

        $ret = true;

        if ( $this->EOT( ) ) {
            return false;
        }

        if ( strlen ($this->m_found_delimiter) ) {

            $this->m_actual_line_delimiter = $this->m_found_delimiter;
            if ($this->m_debug) echo "<br>setting new line delimiter to '$this->m_actual_line_delimiter'";
            $this->m_found_delimiter = '';
        }

        $this->m_next_line = '';
        $pos = $this->m_scriptpos;

        // $this->SkipWhitespaces();

        $fertig = false;
        $testedwhite = false;


        while ( $this->IsWhitespace( $chr = $this->GetChar( ) ) );

        $this->UngetChar( );
//        echo "<be>Nach UngetChar() ist die Position im Textfile auf $this->m_scriptpos und ActChar() liefert >>".$this->ActChar()."<<";

        while( !$fertig) {

            if ( !strlen( $this->m_next_line) ) $this->SkipWhitespaces( );

            $chr=$this->ActChar( );

//            echo "<br>$chr - $this->m_next_line";

            if ( $this->isanfuehrungszeichen( $chr ) ) {
                $this->m_next_line .= $chr;
                $this->FollowBegrenzer( $chr );
            } elseif ( ($chr == '/' ) && ( $this->NextChar( ) == '*') ) {
                $this->SkipComment();
                // $this->SkipWhitespaces();
            } elseif ( $chr == substr($this->m_actual_line_delimiter, 0, 1 ))  {
                // echo sprintf("<br>aktueller Zeilenbegrenzer ist %s mit Länge %s", $this->m_actual_line_delimiter, strlen( $this->m_actual_line_delimiter ) );
                if (strlen($this->m_actual_line_delimiter) == 1) {
                    $this->m_next_line .= $chr;
                    $fertig = true;
                } elseif ( $this->FollowsText( substr( $this->m_actual_line_delimiter, 1 ), true ) ) {
                    // echo "<br>Starte mit<br>$this->m_next_line";
                    $this->m_next_line .= $chr;
                    for ( $i=0; $i < strlen( substr( $this->m_actual_line_delimiter, 1 ) ); $i++ ) $this->m_next_line .= $this->GetChar( );
                    // echo "<br>Ende mit<br>$this->m_next_line";
                    $fertig = true;
                } else {
                    $this->m_next_line .= $chr;
                }

                // echo "<br>$this->m_next_line und $fertig";
                // exit;
            } else {
                $this->m_next_line .= $chr;
            }
            // echo $chr;

//            echo "<br>nextline = >>" . $this->m_next_line ."<<";
            // assert( !$this->IsWhitespace( substr( $this->m_next_line, 0,1) ) );

            if ( strtoupper($this->m_next_line) == "DELIMITER" ) {
                if ($this->m_debug) echo "<br>Found : <b>DELIMITER</b>";
                // $this->m_next_line .= $this->GetChar();
                $this->m_next_line .= ' ';
                while ($this->IsWhitespace($this->GetChar())) ;

                $delimiter = '';
                while ( ! $this->IsWhitespace( $chr = $this->ActChar() ) ) {
                    $this->m_next_line .= $chr;
                    $delimiter .= $chr;
                    $chr = $this->GetChar();
                }
                $this->UngetChar();
                if ($this->m_debug) echo "<br> new delimiter is '$delimiter'";
                $this->m_found_delimiter = $delimiter;
                $fertig = true;
            }

            if ( $this->EOT( ) ) {
                $fertig = true;
            }

            if (!$fertig) $chr = $this->GetChar();

        }

        // echo "<br>Zeile gefunden : $this->m_next_line";

        $ret = true;

        return $ret;
    }


    /**
      *
      * The method ExecuteNextLine( ) executes the line found by GetNextLine( )
      *
      * Example:
      *
      * @return bool true, if the line could be executed without an error
      *
      */


    protected function ExecuteNextLine( ) {

        $ret = true;

        if ($this->m_debug) echo "<br>ExecuteNextLine() mit<br>$this->m_next_line<br>";

        $query = $this->m_next_line;

        if ( !strlen( $query ) ) return true;

        if ( strtoupper( substr( $query, 0, 5 ) == 'BEGIN' ) ) {
            $this->m_mysqli->autocommit( FALSE );
            if ($this->m_debug) echo '<br>emuliere BEGIN TRANSACTION';
            return true;
        } elseif (strtoupper(substr( $query, 0, 6 )) == 'COMMIT' ) {
            if ($this->m_debug) echo '<br>emuliere COMMIT';
            $this->m_mysqli->commit( );
            return true;
        } elseif (strtoupper(substr( $query, 0, 8 )) == 'ROLLBACK' ) {
            if ($this->m_debug) echo '<br>emuliere ROLLBACK';
            $this->m_mysqli->rollback( );
            return true;
        }

        if ( strtoupper( substr( $query, 0, 9 ) ) == 'DELIMITER' ) {
            if ($this->m_debug) echo '<br>DELIMITER gefunden';
            return true;
        }

        // if ( strtoupper( substr( $query, 0, 9 ) ) == 'DELIMITER' ) echo "<br>Found DELIMITER";


        if ( ( $this->m_actual_line_delimiter == ';' ) && ( strtoupper( substr( $query, 0, 9 ) ) != 'DELIMITER' ) ) {
            if ($this->m_debug) echo "<br>arbeite mit query()";
            $result = $this->m_mysqli->query( $query );   // multi_query läßt uns auch Kommandos mit DELIMITER und CREATE TRIGGER absetzen
            if (!$result) {
                printf( id2msg( 1900 ) , "<br>cSqlScript::Execute() mit query()", $this->m_mysqli->sqlstate, $this->m_mysqli->error);
                echo "<br>SQL State = " . $this->m_mysqli->sqlstate;
                echo "<br>Error = " . $this->m_mysqli->error;
                $ret =false;
            }
        } else {
            if ($this->m_debug) echo "<br>arbeite mit multi_query()";

            $query = substr( $query, 0 , strlen( $query ) - strlen( $this->m_actual_line_delimiter ) );
            if ($this->m_debug) echo "<br>query ohne delimiter =<br>$query<br>";
            // $query .= "\n";
            $result = $this->m_mysqli->multi_query( $query );   // multi_query läßt uns auch Kommandos mit DELIMITER und CREATE TRIGGER absetzen
            if (!$result) {
                printf( id2msg( 1900 ) , "<br>cSqlScript::Execute( mit multi_query())", $this->m_mysqli->sqlstate, $this->m_mysqli->error);
                echo "<br>SQL State = " . $this->m_mysqli->sqlstate;
                echo "<br>Error = " . $this->m_mysqli->error;
                if ( strlen($this->m_mysqli->error)) $ret =false;
            }

        }

        if ( strlen($this->m_mysqli->error)) echo "<br>" . $this->m_mysqli->error;
        if ( !$ret ) echo "<hr><br>$query<br><hr>" . $this->m_mysqli->error;

        return $ret;

    }   // function ExecuteNextLine()


    /**
      *
      * The method GetTableNameInNextLine( ) searches for a CREATE TABLE statement in $m_next_line
      *
      * Example:
      *
      * @return string the table name or an empty string
      *
      */


    public function GetTableNameInNextLine( ) {

        $ret = '';
        $pos = 0;

        if ( strtoupper(substr( $this->m_next_line, 0, 6)) == 'CREATE' ) {
            $pos = 7;
            while ( $this->IsWhitespace( substr( $this->m_next_line, $pos, 1) ) ) { $pos ++; }
// echo "<br>-->" . $pos . " - " . strtoupper(substr( $this->m_next_line, $pos, 5)) . " - " . strlen(strtoupper(substr( $this->m_next_line, $pos,  5)));
            if ( strtoupper(substr( $this->m_next_line, $pos, 5)) == 'TABLE' ) {
                $pos += 5;
                while ( $this->IsWhitespace( substr( $this->m_next_line, $pos, 1) ) ) { $pos ++; }
                if ( $this->isanfuehrungszeichen (substr( $this->m_next_line, $pos, 1) ) ) $pos++;
                $startpos = $pos;
                assert ( $this->isidstart( substr( $this->m_next_line, $pos, 1) ) );
                while ( $this->isidnext( substr( $this->m_next_line, $pos, 1) )  ) { $pos ++; }
                $tablename = substr( $this->m_next_line, $startpos, $pos - $startpos );
                if ($this->m_debug) echo "<br>ermittelter Tablename = '$tablename'";
                $ret = $tablename;

            }
        }

        return $ret;

    }   // function GetTableNameInNextLine( )

    /**
      *
      * The method ExecuteScript( ) executes the statements found in the script line by line
      *
      * Example:
      *
      * @return bool true, if the script could be executed without errors
      *
      */


    public function ExecuteScript() {
        $ret = true;
        $line = 0;

        $this->m_mysqli = OpenTBVSDB();

        while ($ret && $this->GetNextLine() ) {
            $line ++;
            if ( ! $this->ExecuteNextLine( ) ) {
                throw new \Exception( "<br>Fehler beim Ausf&uuml;hren der SQL-Zeile :<br>" . $this->m_next_line );
                $ret = false;
            }

            if ( strtoupper(substr( $this->m_next_line, 0, 6)) == 'CREATE' ) {
                $tablename = $this->GetTableNameInNextLine();
                if (strlen($tablename)) $this->m_table_names[] = $tablename;
            }

            // if ( $line >30 ) exit;

        }

        $this->m_mysqli->close( );

        for ( $i = 0; $i< sizeof( $this->m_table_names ); $i++ ) {
            $tablename = $this->m_table_names[ $i ];
            if ( ! self::ExistsTable( $tablename ) ) echo "<br>hat Tabelle '$tablename' nicht angelegt !"; else echo "<br>hat Tabelle '$tablename' angelegt !";
        }

        if ($this->m_debug) echo "<br>ExecuteScript() abgeschlossen";


        return $ret;
    }   // function ExecuteScript()

    /**
      *
      * The method ExistsTable( ) checks whether a table exists or not
      *
      * Example:
      *
      * @param mysqli $mysqli the database connection
      * @param string $tablename is the name of the table to check
      *
      * @return bool true, if the table $tablename exists
      *
      */

    static protected function ExistsTable( $mysqli, $tablename ) {

	assert( is_a( $m_mysqli, 'mysqli' ) );
	assert( is_string( $tablename ) );
	assert( strlen( trim( $tablename ) ) );

	$query = "SHOW TABLES LIKE '$tablename'";

	$result = $mysqli->query( $query );
	if ( $result !== false ) {

	    $found = ( $result->num_rows == 1 );
	    $result->close();

	} else {
	    throw new \Exception( "error when executing sql '$query'" );
	}

	return $found;

    }   // function ExistsTable()

}   // class cSqlScript



?>