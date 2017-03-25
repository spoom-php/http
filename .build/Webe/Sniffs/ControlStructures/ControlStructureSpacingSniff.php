<?php

class Webe_Sniffs_ControlStructures_ControlStructureSpacingSniff implements PHP_CodeSniffer_Sniff {

  /**
   * How many spaces should follow the opening bracket.
   *
   * @var int
   */
  public $requiredSpacesAfterOpen = 1;

  /**
   * How many spaces should precede the closing bracket.
   *
   * @var int
   */
  public $requiredSpacesBeforeClose = 1;

  /**
   * Returns an array of tokens this test wants to listen for.
   *
   * @return array
   */
  public function register() {
    return array(
      T_IF,
      T_WHILE,
      T_FOREACH,
      T_FOR,
      T_SWITCH,
      T_DO,
      T_ELSE,
      T_ELSEIF,
      T_TRY,
      T_CATCH,
    );

  }//end register()

  /**
   * Processes this test, when one of its tokens is encountered.
   *
   * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
   * @param int                  $stackPtr  The position of the current token
   *                                        in the stack passed in $tokens.
   *
   * @return void
   */
  public function process( PHP_CodeSniffer_File $phpcsFile, $stackPtr ) {
    $this->requiredSpacesAfterOpen   = (int) $this->requiredSpacesAfterOpen;
    $this->requiredSpacesBeforeClose = (int) $this->requiredSpacesBeforeClose;
    $tokens                          = $phpcsFile->getTokens();

    if( isset( $tokens[ $stackPtr ][ 'parenthesis_opener' ] ) === false
      || isset( $tokens[ $stackPtr ][ 'parenthesis_closer' ] ) === false
    ) {
      return;
    }

    $parenOpener    = $tokens[ $stackPtr ][ 'parenthesis_opener' ];
    $parenCloser    = $tokens[ $stackPtr ][ 'parenthesis_closer' ];
    $spaceAfterOpen = 0;
    if( $tokens[ ( $parenOpener + 1 ) ][ 'code' ] === T_WHITESPACE ) {
      if( strpos( $tokens[ ( $parenOpener + 1 ) ][ 'content' ], $phpcsFile->eolChar ) !== false ) {
        $spaceAfterOpen = 'newline';
      } else {
        $spaceAfterOpen = strlen( $tokens[ ( $parenOpener + 1 ) ][ 'content' ] );
      }
    }

    $phpcsFile->recordMetric( $stackPtr, 'Spaces after control structure open parenthesis', $spaceAfterOpen );

    if( $spaceAfterOpen !== $this->requiredSpacesAfterOpen ) {
      $error = 'Expected %s spaces after opening bracket; %s found';
      $data  = array(
        $this->requiredSpacesAfterOpen,
        $spaceAfterOpen,
      );
      $fix   = $phpcsFile->addFixableError( $error, ( $parenOpener + 1 ), 'SpacingAfterOpenBrace', $data );
      if( $fix === true ) {
        $padding = str_repeat( ' ', $this->requiredSpacesAfterOpen );
        if( $spaceAfterOpen === 0 ) {
          $phpcsFile->fixer->addContent( $parenOpener, $padding );
        } else if( $spaceAfterOpen === 'newline' ) {
          $phpcsFile->fixer->replaceToken( ( $parenOpener + 1 ), '' );
        } else {
          $phpcsFile->fixer->replaceToken( ( $parenOpener + 1 ), $padding );
        }
      }
    }

    if( $tokens[ $parenOpener ][ 'line' ] === $tokens[ $parenCloser ][ 'line' ] ) {
      $spaceBeforeClose = 0;
      if( $tokens[ ( $parenCloser - 1 ) ][ 'code' ] === T_WHITESPACE ) {
        $spaceBeforeClose = strlen( ltrim( $tokens[ ( $parenCloser - 1 ) ][ 'content' ], $phpcsFile->eolChar ) );
      }

      $phpcsFile->recordMetric( $stackPtr, 'Spaces before control structure close parenthesis', $spaceBeforeClose );

      if( $spaceBeforeClose !== $this->requiredSpacesBeforeClose ) {
        $error = 'Expected %s spaces before closing bracket; %s found';
        $data  = array(
          $this->requiredSpacesBeforeClose,
          $spaceBeforeClose,
        );
        $fix   = $phpcsFile->addFixableError( $error, ( $parenCloser - 1 ), 'SpaceBeforeCloseBrace', $data );
        if( $fix === true ) {
          $padding = str_repeat( ' ', $this->requiredSpacesBeforeClose );
          if( $spaceBeforeClose === 0 ) {
            $phpcsFile->fixer->addContentBefore( $parenCloser, $padding );
          } else {
            $phpcsFile->fixer->replaceToken( ( $parenCloser - 1 ), $padding );
          }
        }
      }
    }//end if

  }//end process()

}//end class
