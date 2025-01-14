<?php


namespace M4bTool\Command;


use M4bTool\Audio\Tag;
use M4bTool\Tags\StringBuffer;
use SplFileInfo;
use Symfony\Component\Console\Input\InputOption;

class AbstractConversionCommand extends AbstractCommand
{
    const TAG_DESCRIPTION_MAX_LEN = 255;
    const TAG_DESCRIPTION_SUFFIX = " ...";

    const OPTION_AUDIO_FORMAT = "audio-format";
    const OPTION_AUDIO_CHANNELS = "audio-channels";
    const OPTION_AUDIO_BIT_RATE = "audio-bitrate";
    const OPTION_AUDIO_SAMPLE_RATE = "audio-samplerate";
    const OPTION_AUDIO_CODEC = "audio-codec";
    const OPTION_AUDIO_PROFILE = "audio-profile";
    const OPTION_ADJUST_FOR_IPOD = "adjust-for-ipod";
    const OPTION_SKIP_COVER = "skip-cover";
    const OPTION_COVER = "cover";
    const OPTION_FIX_MIME_TYPE = "fix-mime-type";

    protected $optAudioFormat;
    protected $optAudioExtension;
    protected $optAudioChannels;
    protected $optAudioBitRate;
    protected $optAudioSampleRate;
    protected $optAudioCodec;
    protected $optAdjustBitrateForIpod;

    protected $longDescription;

    public function inputOptionsToTag()
    {
        $tag = new Tag;

        $tag->title = $this->input->getOption("name");
        $tag->album = $this->input->getOption("album");

        // on ipods / itunes, album is for title of the audio book
        if ($this->optAdjustBitrateForIpod && $tag->title && !$tag->album) {
            $tag->album = $tag->title;
        }

        $tag->artist = $this->input->getOption("artist");
        $tag->genre = $this->input->getOption("genre");
        $tag->writer = $this->input->getOption("writer");
        $tag->albumArtist = $this->input->getOption("albumartist");
        $tag->year = $this->input->getOption("year");
        $tag->cover = $this->input->getOption("cover");
        $tag->description = $this->input->getOption("description");
        $tag->longDescription = $this->input->getOption("longdesc");


        $tag->comment = $this->input->getOption("comment");
        $tag->copyright = $this->input->getOption("copyright");
        $tag->encodedBy = $this->input->getOption("encoded-by");


        return $tag;
    }

    protected function configure()
    {
        parent::configure();
        $this->addOption(static::OPTION_AUDIO_FORMAT, null, InputOption::VALUE_OPTIONAL, "output format, that ffmpeg will use to create files", "m4b");
        $this->addOption(static::OPTION_AUDIO_CHANNELS, null, InputOption::VALUE_OPTIONAL, "audio channels, e.g. 1, 2", ""); // -ac 1
        $this->addOption(static::OPTION_AUDIO_BIT_RATE, null, InputOption::VALUE_OPTIONAL, "audio bitrate, e.g. 64k, 128k, ...", ""); // -ab 128k
        $this->addOption(static::OPTION_AUDIO_SAMPLE_RATE, null, InputOption::VALUE_OPTIONAL, "audio samplerate, e.g. 22050, 44100, ...", ""); // -ar 44100
        $this->addOption(static::OPTION_AUDIO_CODEC, null, InputOption::VALUE_OPTIONAL, "audio codec, e.g. libmp3lame, aac, ...", ""); // -ar 44100
        $this->addOption(static::OPTION_AUDIO_PROFILE, null, InputOption::VALUE_OPTIONAL, "audio profile, when using extra low bitrate - valid values (mono, stereo): aac_he, aac_he_v2 ", "");
        $this->addOption(static::OPTION_ADJUST_FOR_IPOD, null, InputOption::VALUE_NONE, "auto adjust bitrate and sampling rate for ipod, if track is to long (may lead to poor quality)");

        $this->addOption("name", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook name, otherwise the existing metadata will be used", "");
        $this->addOption("album", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook album, otherwise the existing metadata for name will be used", "");
        $this->addOption("artist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook artist, otherwise the existing metadata will be used", "");
        $this->addOption("genre", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook genre, otherwise the existing metadata will be used", "");
        $this->addOption("writer", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook writer, otherwise the existing metadata will be used", "");
        $this->addOption("albumartist", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook albumartist, otherwise the existing metadata will be used", "");
        $this->addOption("year", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook year, otherwise the existing metadata will be used", "");
        $this->addOption("cover", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook cover, otherwise the existing metadata will be used", null);
        $this->addOption("description", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook short description, otherwise the existing metadata will be used", null);
        $this->addOption("longdesc", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook long description, otherwise the existing metadata will be used", null);
        $this->addOption("comment", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook comment, otherwise the existing metadata will be used", null);
        $this->addOption("copyright", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook copyright, otherwise the existing metadata will be used", null);
        $this->addOption("encoded-by", null, InputOption::VALUE_OPTIONAL, "provide a custom audiobook encoded-by, otherwise the existing metadata will be used", null);

        $this->addOption(static::OPTION_SKIP_COVER, null, InputOption::VALUE_NONE, "skip extracting and embedding covers", null);
        $this->addOption(static::OPTION_FIX_MIME_TYPE, null, InputOption::VALUE_NONE, "try to fix MIME-type (e.g. from video/mp4 to audio/mp4) - this is needed for some players to prevent video window", null);
    }

    protected function loadArguments()
    {
        parent::loadArguments();

        $this->optAdjustBitrateForIpod = $this->input->getOption(static::OPTION_ADJUST_FOR_IPOD);
        $this->optAudioCodec = $this->input->getOption(static::OPTION_AUDIO_CODEC);
        $this->optAudioFormat = $this->input->getOption(static::OPTION_AUDIO_FORMAT);
        $this->optAudioExtension = $this->optAudioFormat;
        if ($this->optAudioFormat === static::AUDIO_EXTENSION_M4B) {
            $this->optAudioFormat = static::AUDIO_FORMAT_MP4;
        }


        if (!$this->optAudioCodec) {
            if (isset(static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat])) {
                if ($this->optAudioFormat === static::AUDIO_FORMAT_MP4) {
                    $this->optAudioCodec = $this->loadHighestAvailableQualityAacCodec();
                } else {
                    $this->optAudioCodec = static::AUDIO_FORMAT_CODEC_MAPPING[$this->optAudioFormat];
                }
            }
        }


        $this->optAudioChannels = (int)$this->input->getOption(static::OPTION_AUDIO_CHANNELS);
        $this->optAudioBitRate = $this->input->getOption(static::OPTION_AUDIO_BIT_RATE);
        $this->optAudioSampleRate = $this->input->getOption(static::OPTION_AUDIO_SAMPLE_RATE);

    }

    protected function loadHighestAvailableQualityAacCodec()
    {
        // libfdk_aac (best quality)
        // libfaac (high quality)
        // aac -strict experimental (decent quality, but use higher bitrates)
        // libvo_aacenc (bad quality)

        $aacQualityOrder = [
            "libfdk_aac",
            "libfaac",
            "aac"
        ];

        $process = $this->ffmpeg(["-hide_banner", "-codecs"], "determine highest available audio codec");
        $process->stop(10);
        /*
Codecs:
 D..... = Decoding supported
 .E.... = Encoding supported
 ..V... = Video codec
 ..A... = Audio codec
 ..S... = Subtitle codec
 ...I.. = Intra frame-only codec
 ....L. = Lossy compression
 .....S = Lossless compression
 -------
 D.VI.. 012v                 Uncompressed 4:2:2 10-bit
 D.V.L. 4xm                  4X Movie
 D.VI.S 8bps                 QuickTime 8BPS video
 .EVIL. a64_multi            Multicolor charset for Commodore 64 (encoders: a64multi )
 .EVIL. a64_multi5           Multicolor charset for Commodore 64, extended with 5th color (colram) (encoders: a64multi5 )
 D.V..S aasc                 Autodesk RLE
 D.VIL. aic                  Apple Intermediate Codec
 DEVI.S alias_pix            Alias/Wavefront PIX image
 DEVIL. amv                  AMV Video
         */
//        $aacQualityOrder
        $codecOutput = $process->getOutput() . $process->getErrorOutput();

        $index = 1;
        $returnValue = "libvo_aacenc";
        foreach ($aacQualityOrder as $index => $encoderName) {
            if (preg_match("/\b" . preg_quote($encoderName) . "\b/i", $codecOutput)) {
                $returnValue = $encoderName;
                break;
            }
        }

        if ($index > 0) {
            $this->output->writeln("Your ffmpeg version cannot produce top quality aac using encoder " . $returnValue . " instead of " . $aacQualityOrder[0] . "");
        }

        return $returnValue;
    }

    protected function tagFile(SplFileInfo $file, Tag $tag)
    {
        if ($this->input->getOption(static::OPTION_FIX_MIME_TYPE)) {
            $this->fixMimeType($file);
        }

        if ($this->optAudioFormat === static::AUDIO_FORMAT_MP4) {
            $command = [];

            $this->adjustTagDescriptionForMp4($tag);

            $this->appendParameterToCommand($command, "-track", $tag->track);
            $this->appendParameterToCommand($command, "-tracks", $tag->tracks);
            $this->appendParameterToCommand($command, "-song", $tag->title);
            $this->appendParameterToCommand($command, "-artist", $tag->artist);
            $this->appendParameterToCommand($command, "-genre", $tag->genre);
            $this->appendParameterToCommand($command, "-writer", $tag->writer);
            $this->appendParameterToCommand($command, "-description", $tag->description);
            $this->appendParameterToCommand($command, "-longdesc", $tag->longDescription);
            $this->appendParameterToCommand($command, "-albumartist", $tag->albumArtist);
            $this->appendParameterToCommand($command, "-year", $tag->year);
            $this->appendParameterToCommand($command, "-album", $tag->album);
            $this->appendParameterToCommand($command, "-comment", $tag->comment);
            $this->appendParameterToCommand($command, "-copyright", $tag->copyright);
            $this->appendParameterToCommand($command, "-encodedby", $tag->encodedBy);
            $this->appendParameterToCommand($command, "-type", Tag::MP4_STIK_AUDIOBOOK);


            if (count($command) > 1) {
                $command[] = $file;
                $this->mp4tags($command, "tagging file " . $file);
            }

            if ($tag->cover && !$this->input->getOption(static::OPTION_SKIP_COVER)) {
                if (!file_exists($tag->cover)) {
                    $this->output->writeln("cover file " . $tag->cover . " does not exist");
                    return;
                }
                $command = ["--add", $tag->cover, $file];
                $this->appendParameterToCommand($command, "-f", $this->optForce);
                $process = $this->mp4art($command, "adding cover " . $tag->cover . " to " . $file);
                $this->debug($process->getOutput() . $process->getErrorOutput());
            }

            return;
        }

        if ($this->optAudioFormat === static::AUDIO_FORMAT_MP3) {
            $this->appendTemplateParameterToCommand($command, '-metadata title="%s"', $tag->title);
        }
    }

    private function adjustTagDescriptionForMp4(Tag $tag)
    {
        if (!$tag->description) {
            return;
        }

        $description = $tag->description;
        $encoding = $this->detectEncoding($description);
        if ($encoding === "") {
            $this->output->writeln("could not detect encoding of description, using UTF-8 as default");
        } else if ($encoding !== "UTF-8") {
            $description = mb_convert_encoding($tag->description, "UTF-8", $encoding);
        }


        $stringBuf = new StringBuffer($description);
        if ($stringBuf->byteLength() <= static::TAG_DESCRIPTION_MAX_LEN) {
            return;
        }

        $tag->description = $stringBuf->softTruncateBytesSuffix(static::TAG_DESCRIPTION_MAX_LEN, static::TAG_DESCRIPTION_SUFFIX);

        if (!$tag->longDescription) {
            $tag->longDescription = (string)$stringBuf;
        }
    }

    /**
     * mb_detect_encoding is not reliable on all systems and leads to php errors in some cases
     *
     * @param $string
     * @return string
     */
    private function detectEncoding($string)
    {
        if (preg_match("//u", $string)) {
            return "UTF-8";
        }

        $encodings = [
            'UTF-8', 'ASCII', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
            'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13',
            'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16', 'Windows-1251', 'Windows-1252', 'Windows-1254',
        ];

        // $enclist = mb_list_encodings();

        foreach ($encodings as $encoding) {
            $sample = mb_convert_encoding($string, $encoding, $encoding);
            if (md5($sample) === md5($string)) {
                return $encoding;
            }
        }

        return "";
    }

    protected function bitrateStringToInt()
    {
        $multipliers = [
            "k" => 1000,
            "M" => 1000 * 1000,
            "G" => 1000 * 1000 * 1000,
            "T" => 1000 * 1000 * 1000 * 1000,
        ];
        preg_match("/^([0-9]+)[\s]*(" . implode("|", array_keys($multipliers)) . ")[\s]*$/U", $this->optAudioBitRate, $matches);

        if (count($matches) !== 3) {
            throw new \Exception("Invalid audio-bitrate: " . $this->optAudioBitRate);
        }
        $value = $matches[1];
        $multiplier = $multipliers[$matches[2]];
        return $value * $multiplier;
    }

    protected function samplingRateToInt()
    {
        return (int)str_ireplace("hz", "", $this->optAudioSampleRate);
    }

    protected function appendFfmpegTagParametersToCommand(&$command, Tag $tag)
    {
        if ($tag->title) {
            $command[] = '-metadata';
            $command[] = 'title=' . $tag->title;
        }

        if ($tag->artist) {
            $command[] = '-metadata';
            $command[] = 'artist=' . $tag->artist;
        }


        if ($tag->album) {
            $command[] = '-metadata';
            $command[] = 'album=' . $tag->album;
        }


        if ($tag->genre) {
            $command[] = '-metadata';
            $command[] = 'genre=' . $tag->genre;
        }

        if ($tag->description) {
            $command[] = '-metadata';
            $command[] = 'description=' . $tag->description;
        }

        if ($tag->writer) {
            $command[] = '-metadata';
            $command[] = 'composer=' . $tag->writer;
        }


        if ($tag->track && $tag->tracks) {
            $command[] = '-metadata';
            $command[] = 'track=' . $tag->track . "/" . $tag->tracks;
        }

        if ($tag->albumArtist) {
            $command[] = '-metadata';
            $command[] = 'album_artist=' . $tag->albumArtist;
        }


        if ($tag->year) {
            $command[] = '-metadata';
            $command[] = 'date=' . $tag->year;
        }

        if ($tag->comment) {
            $command[] = '-metadata';
            $command[] = 'comment=' . $tag->comment;
        }


        if ($tag->copyright) {
            $command[] = '-metadata';
            $command[] = 'copyright=' . $tag->copyright;
        }


        if ($tag->encodedBy) {
            $command[] = '-metadata';
            $command[] = 'encoded_by=' . $tag->encodedBy;
        }
    }

    protected function extractCover(SplFileInfo $file, SplFileInfo $coverTargetFile, $force = false)
    {
        if (!$file->isFile()) {
            $this->output->writeln("skip cover extraction, source file " . $file . " does not exist");
            return null;
        }

        if ($coverTargetFile->isFile() && !$force) {
            $this->output->writeln("skip cover extraction, file " . $coverTargetFile . " already exists - use --force to overwrite");
            return null;
        }
        if ($this->input->getOption(static::OPTION_SKIP_COVER)) {
            return null;
        }

        if ($this->input->getOption(static::OPTION_COVER)) {
            return null;
        }

        $this->ffmpeg(["-i", $file, "-an", "-vcodec", "copy", $coverTargetFile], "try to extract cover from " . $file);
        if (!$coverTargetFile->isFile()) {
            $this->output->writeln("extracting cover to " . $coverTargetFile . " failed");
            return null;
        }

        return $coverTargetFile;
    }

    protected function extractDescription(Tag $tag, SplFileInfo $descriptionTargetFile)
    {
        if ($descriptionTargetFile->isFile() && !$this->optForce) {
            $this->output->writeln("skip description extraction, file " . $descriptionTargetFile . " already exists - use --force to overwrite");
            return null;
        }

        if (!$tag->description && !$tag->longDescription) {
            $this->output->writeln("skip description extraction, tag does not contain a description");
            return null;
        }

        $description = $tag->description ? $tag->description : $tag->longDescription;
        if (file_put_contents($descriptionTargetFile, $description) === false) {
            $this->output->writeln("extracting description to " . $descriptionTargetFile . " failed");
            return null;
        };
        return $descriptionTargetFile;
    }
}
