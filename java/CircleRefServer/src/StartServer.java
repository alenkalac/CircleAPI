
public class StartServer {

    ProcessRequests pRequests;

    public StartServer() {
        pRequests = new ProcessRequests();

        Thread th = new Thread(pRequests);
        th.start();

        Thread fetcher = new Thread(new RequestFetcher(pRequests));
        fetcher.start();
    }

    public static void main(String args[]) {
        new StartServer();
    }
}
