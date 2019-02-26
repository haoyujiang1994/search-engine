import java.io.*;
import org.apache.tika.exception.TikaException;
import org.apache.tika.metadata.Metadata;
import org.apache.tika.parser.ParseContext;
import org.apache.tika.parser.html.HtmlParser;
import org.apache.tika.sax.BodyContentHandler;
import org.xml.sax.SAXException;

public class bigtext {

    public static void main(String[] args) throws IOException,TikaException,SAXException {

        String dirpath = "/Users/daisy/solr-7.5.0/latimes";
        File dir = new File(dirpath);

        PrintWriter writer = new PrintWriter("/Users/daisy/Desktop/hw5/big.txt", "UTF-8");


        for (File file: dir.listFiles()){

            //detecting the file type
            BodyContentHandler bhandler = new BodyContentHandler(-1);
            Metadata mdata = new Metadata();
            FileInputStream fstream = new FileInputStream(file);
            ParseContext pcontext = new ParseContext();

            //Html parser
            HtmlParser hparser = new HtmlParser();
            hparser.parse(fstream, bhandler, mdata, pcontext);

        
            writer.println(bhandler.toString());

        }
       writer.flush();
       writer.close();

    }
}

