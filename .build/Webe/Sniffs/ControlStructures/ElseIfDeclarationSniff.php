<?php

class Webe_Sniffs_ControlStructures_ElseIfDeclarationSniff implements PHP_CodeSniffer_Sniff {

  /**
   * Returns an array of tokens this test wants to listen for.
   *
   * @return array
   */
  public function register() {
    return array(
      T_ELSE,
      T_ELSEIF,
    );

  }//end register()

  /**
   * Processes this test, when one of its tokens is encountered.
   *
   * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
   * @param int                  $stackPtr  The position of the current token in the
   *                                        stack passed in $tokens.
   *
   * @return void
   */
  public function process( PHP_CodeSniffer_File $phpcsFile, $stackPtr ) {
    $tokens = $phpcsFile->getTokens();

    if( $tokens[ $stackPtr ][ 'code' ] === T_ELSEIF ) {
      $phpcsFile->recordMetric( $stackPtr, 'Use of ELSE IF or ELSEIF', 'elseif' );

      $error = 'Usage of ELSEIF is discouraged; use ELSE IF instead';
      $fix   = $phpcsFile->addFixableWarning( $error, $stackPtr, 'NotAllowed' );

      if( $fix === true ) {
        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken( $stackPtr, 'else if' );
        $phpcsFile->fixer->endChangeset();
      }

      return;
    }

    $next = $phpcsFile->findNext( T_WHITESPACE, ( $stackPtr + 1 ), null, true );
    if( $tokens[ $next ][ 'code' ] === T_IF ) {
      $phpcsFile->recordMetric( $stackPtr, 'Use of ELSE IF or ELSEIF', 'else if' );
    }

  }//end process()

}//end class
