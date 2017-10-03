import com.google.gson.Gson;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import org.json.simple.JSONArray;
import org.json.simple.parser.JSONParser;
import org.json.simple.parser.ParseException;

import java.io.IOException;

public class RequestFetcher implements Runnable {

    private int lastID;
    private ProcessRequests pRequsts;
    private OkHttpClient client;
    private JSONParser parser;

    public RequestFetcher(ProcessRequests pRequsts) {
        System.out.println("STARTING FETCH SERVICE");
        this.pRequsts = pRequsts;
        lastID = 0; //API CALL TO GET THE LAST PROCESSED ID.

        this.client = new OkHttpClient();
    }
    private void sleep(int millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }

    private void doFetch() {
        Request request = new Request.Builder().url("http://localhost/fetch/requests").build();

        try {
            Response response = client.newCall(request).execute();

            String data = response.body().string();

            FetchResponse[] res = new Gson().fromJson(data, FetchResponse[].class);

            for(FetchResponse r : res ) {
                pRequsts.addRequest(new RequestTransaction(r));
            }

        }catch(IOException e) {
            e.printStackTrace();
        }
    }

    public void run() {
        while(true) {
            doFetch();
            this.sleep(5000);
        }
    }
}
