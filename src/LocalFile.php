<?php
namespace SequelONE\LocalFile;

use SequelONE\LocalFile\Exceptions\CantWriteToReadOnlyDirectory;
use SequelONE\LocalFile\Exceptions\SourceFileDoesNotExist;
use SequelONE\LocalFile\Exceptions\UnableToOpenFile;
use SequelONE\LocalFile\Exceptions\UnableToOpenSplitFileHandle;
use SequelONE\LocalFile\Exceptions\UnableToReadFile;
use SequelONE\LocalFile\Exceptions\UnableToWriteLineToSplitFile;

class LocalFile {

    /**
     * A php function that returns the number of lines in a file on the local file system.
     *
     * @param string $filePath
     *
     * @return int
     * @throws \SequelONE\LocalFile\Exceptions\UnableToOpenFile
     */
    public static function lineCount( string $filePath ): int {

        $lineCount = 0;
        $handle    = @fopen( $filePath, "r" );
        if ( $handle === false ) {
            throw new UnableToOpenFile( "Unable to open the file at: " . $filePath );
        }
        while ( ! feof( $handle ) ) {
            fgets( $handle );
            $lineCount++;
        }

        fclose( $handle );
        return $lineCount;
    }



    /**
     * @param string $pathToSourceFile
     * @param int $linesPerFile
     * @param string|null $prefix
     * @param string|null $destinationPath
     * @param string $DIRECTORY_SEPARATOR
     * @return array An array of the paths to the split files.
     * @throws CantWriteToReadOnlyDirectory
     * @throws SourceFileDoesNotExist
     * @throws UnableToOpenSplitFileHandle
     * @throws UnableToReadFile
     * @throws UnableToWriteLineToSplitFile
     */
    public static function split( string $pathToSourceFile,
                                  $linesPerFile = 1000,
                                  string $prefix = null,
                                  string $destinationPath = null,
                                  string $DIRECTORY_SEPARATOR = DIRECTORY_SEPARATOR): array {

        if ( false === file_exists( $pathToSourceFile ) ):
            throw new SourceFileDoesNotExist( "Can't split [" . $pathToSourceFile . "] because it doesn't exist." );
        endif;

        $sourceFileParts     = pathinfo( $pathToSourceFile );
        $sourceDirectory     = $sourceFileParts[ 'dirname' ];
        $sourceFileName      = $sourceFileParts[ 'filename' ];
        $sourceFileExtension = $sourceFileParts[ 'extension' ];

        /**
         * Suppress the error here, because on failure I will throw a custom exception.
         */
        $sourceHandle = @fopen( $pathToSourceFile, "r" );

        if ( false === $sourceHandle ):
            throw new UnableToReadFile( "Unable to read the source file at [" . $pathToSourceFile . "]" );
        endif;

        if ( is_null( $destinationPath ) ):
            $destinationPath = $sourceDirectory;
        endif;

        /**
         * Make sure there is a trailing DIRECTORY_SEPARATOR
         */
        if ( $DIRECTORY_SEPARATOR != substr( $destinationPath, -1 ) ):
            $destinationPath .= $DIRECTORY_SEPARATOR;
        endif;

        if ( false === is_writeable( $destinationPath ) ):
            throw new CantWriteToReadOnlyDirectory( "Can't split [" . $pathToSourceFile . "] because the destination path at [" . $destinationPath . "] is not writeable." );
        endif;

        $totalLineCount        = 0;
        $currentChunkLineCount = 0;
        $totalChunkCount       = 0;
        $splitFilePaths        = [];

        while ( false !== ( $line = fgets( $sourceHandle ) ) ):

            $line = trim( $line );

            // process the line read.
            if ( 0 === $currentChunkLineCount ):
                // Create new split file.
                $totalChunkCount++;
                $suffix             = $totalChunkCount;
                $newSplitFileName   = $prefix . $sourceFileName . '_' . $suffix . '.' . $sourceFileExtension;
                $newSplitFilePath   = $destinationPath . $newSplitFileName;
                $newSplitFileHandle = fopen( $newSplitFilePath, 'w' );
                $splitFilePaths[]   = $newSplitFilePath;
            endif;

            if ( isset( $newSplitFileHandle ) && false !== $newSplitFileHandle ):
                $bytesWritten = fwrite( $newSplitFileHandle, $line . PHP_EOL );

                if ( false === $bytesWritten ):
                    throw new UnableToWriteLineToSplitFile( "Unable to write line #" . ( $totalLineCount + 1 ) . " from " . $pathToSourceFile . " to split file named " . $newSplitFileName );
                endif;

                $totalLineCount++;
                $currentChunkLineCount++;

                if ( $currentChunkLineCount >= $linesPerFile ):
                    //@ftruncate( $newSplitFileHandle, -1 );
                    @fclose( $newSplitFileHandle );
                    $currentChunkLineCount = 0;
                endif;
            else:
                throw new UnableToOpenSplitFileHandle( "Unable to open the file handle to a new split file named [" . $newSplitFileName . "]" );
            endif;
        endwhile;


        // Remove the trailing newline.
        if ( isset( $newSplitFileHandle ) ):
            //@ftruncate( $newSplitFileHandle, -1 );
            @fclose( $newSplitFileHandle ); // Just in case.
        endif;


        @fclose( $sourceHandle );

        return $splitFilePaths;
    }
}